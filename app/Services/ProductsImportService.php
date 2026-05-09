<?php

namespace App\Services;

use App\Imports\ProductsImport;
use App\Models\Category;
use App\Models\UnidadMedida;
use App\Models\Products;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class ProductsImportService
{
    /**
     * Tiempo de vida en minutos del archivo temporal.
     * Si el usuario hace preview pero nunca confirma, los archivos
     * mas viejos que esto seran eliminados en la siguiente operacion.
     */
    protected int $ttlMinutos = 60;

    /**
     * Procesa el archivo subido, valida cada fila y guarda el archivo
     * temporalmente. Devuelve el resumen del preview con un token.
     */
    public function preview(UploadedFile $file, int $establecimientoId): array
    {
        // Antes de procesar limpiamos archivos viejos que el usuario nunca confirmo
        $this->limpiarArchivosVencidos($establecimientoId);

        // Leemos el archivo en memoria
        $importer = new ProductsImport();
        Excel::import($importer, $file);
        $rows = $importer->getRows();

        if (empty($rows)) {
            return [
                'token'        => null,
                'total_filas'  => 0,
                'validas'      => 0,
                'con_errores'  => 0,
                'errores'      => [],
                'mensaje'      => 'El archivo no contiene filas para procesar.',
            ];
        }

        // Precargamos catalogos del establecimiento una sola vez
        $catalogos = $this->cargarCatalogos($establecimientoId);

        // Validamos todas las filas
        $resultado = $this->validarFilas($rows, $catalogos, $establecimientoId);

        // Guardamos el archivo solo si hay al menos una fila valida que importar
        // Si todo fallo, no tiene sentido guardar nada
        $token = null;
        if ($resultado['validas'] > 0) {
            $token = $this->guardarArchivoTemporal($file, $establecimientoId);
        }

        return [
            'token'       => $token,
            'total_filas' => count($rows),
            'validas'     => $resultado['validas'],
            'con_errores' => $resultado['con_errores'],
            'errores'     => $resultado['errores'],
        ];
    }

    /**
     * Ejecuta la importacion definitiva usando el token del preview.
     * Devuelve el array de filas que fallaron para que el controller
     * genere el archivo de errores y lo descargue.
     * Siempre elimina el archivo temporal al final.
     */
    public function ejecutar(string $token, int $establecimientoId): array
    {
        $rutaArchivo = $this->rutaArchivoTemporal($token, $establecimientoId);

        if (!Storage::disk('local')->exists($rutaArchivo)) {
            throw new Exception('El archivo ya no esta disponible. Vuelve a subir el archivo para procesar.');
        }

        try {
            // Releemos el archivo temporal
            $importer = new ProductsImport();
            Excel::import($importer, Storage::disk('local')->path($rutaArchivo));
            $rows = $importer->getRows();

            // Recargamos catalogos en caso de que algo haya cambiado entre preview y ejecucion
            $catalogos = $this->cargarCatalogos($establecimientoId);

            // Volvemos a validar y separamos validas/fallidas
            $resultado = $this->validarFilas($rows, $catalogos, $establecimientoId);

            $insertados = 0;
            if (!empty($resultado['filas_validas'])) {
                $insertados = $this->insertarPorLotes($resultado['filas_validas'], $catalogos, $establecimientoId);
            }

            return [
                'insertados'      => $insertados,
                'fallidas'        => $resultado['filas_fallidas'],
                'total_fallidas'  => count($resultado['filas_fallidas']),
            ];
        } finally {
            // Garantizamos eliminacion del archivo temporal pase lo que pase
            Storage::disk('local')->delete($rutaArchivo);
        }
    }

    /**
     * Carga categorias y unidades del establecimiento en estructuras
     * indexadas por nombre normalizado para validacion rapida sin
     * golpear la BD por cada fila.
     */
    private function cargarCatalogos(int $establecimientoId): array
    {
        $categorias = Category::where('establecimiento_id', $establecimientoId)
            ->get(['id', 'nombre'])
            ->mapWithKeys(fn ($c) => [$this->normalizar($c->nombre) => $c->id])
            ->toArray();

        // Para unidades guardamos dos formas: "unidad" y "unidad (abreviatura)"
        // porque el dropdown del template muestra el formato con abreviatura
        $unidadesRaw = UnidadMedida::where('establecimiento_id', $establecimientoId)
            ->get(['id', 'unidad', 'abreviatura']);

        $unidades = [];
        foreach ($unidadesRaw as $u) {
            $unidades[$this->normalizar($u->unidad)] = $u->id;
            $unidades[$this->normalizar($u->unidad . ' (' . $u->abreviatura . ')')] = $u->id;
        }

        // Codigos y claves ya existentes en BD para validar duplicados
        $codigosExistentes = Products::where('establecimiento_id', $establecimientoId)
            ->whereNotNull('codigo')
            ->pluck('codigo')
            ->map(fn ($c) => strtoupper($c))
            ->flip()
            ->toArray();

        $clavesExistentes = Products::where('establecimiento_id', $establecimientoId)
            ->whereNotNull('clave')
            ->pluck('clave')
            ->map(fn ($c) => strtoupper($c))
            ->flip()
            ->toArray();

        return [
            'categorias'         => $categorias,
            'unidades'           => $unidades,
            'codigos_existentes' => $codigosExistentes,
            'claves_existentes'  => $clavesExistentes,
        ];
    }

    /**
     * Recorre todas las filas, valida cada una y separa las validas
     * de las que tienen errores. Devuelve estructura con conteos,
     * errores por fila y los datos listos para insertar.
     */
    private function validarFilas(array $rows, array $catalogos, int $establecimientoId): array
    {
        $filasValidas  = [];
        $filasFallidas = [];
        $errores       = [];

        // Sets locales para detectar duplicados dentro del mismo archivo
        $codigosArchivo = [];
        $clavesArchivo  = [];

        foreach ($rows as $row) {
            $numeroFila = $row['fila'];
            $data       = $row['data'];
            $erroresFila = [];

            // Normalizamos los valores aceptando que vengan como null
            $codigo       = $this->limpiar($data['codigo'] ?? null);
            $clave        = $this->limpiar($data['clave'] ?? null);
            $nombre       = $this->limpiar($data['nombre'] ?? null);
            $categoria    = $this->limpiar($data['categoria'] ?? null);
            $unidadMedida = $this->limpiar($data['unidad_medida'] ?? null);
            $stock        = $data['stock'] ?? null;
            $precioCompra = $data['precio_compra'] ?? null;
            $precioVenta  = $data['precio_venta'] ?? null;
            $esServicio   = $this->limpiar($data['es_servicio'] ?? null);
            $iva          = $data['iva'] ?? null;
            $descripcion  = $this->limpiar($data['descripcion'] ?? null);

            // nombre obligatorio
            if (empty($nombre)) {
                $erroresFila[] = 'El nombre es obligatorio.';
            } elseif (mb_strlen($nombre) > 255) {
                $erroresFila[] = 'El nombre no puede exceder 255 caracteres.';
            }

            // categoria obligatoria y debe existir
            $categoriaId = null;
            if (empty($categoria)) {
                $erroresFila[] = 'La categoria es obligatoria.';
            } else {
                $key = $this->normalizar($categoria);
                if (!isset($catalogos['categorias'][$key])) {
                    $erroresFila[] = "La categoria '{$categoria}' no existe en este establecimiento.";
                } else {
                    $categoriaId = $catalogos['categorias'][$key];
                }
            }

            // unidad opcional, pero si viene debe existir
            $unidadMedidaId = null;
            if (!empty($unidadMedida)) {
                $key = $this->normalizar($unidadMedida);
                if (!isset($catalogos['unidades'][$key])) {
                    $erroresFila[] = "La unidad de medida '{$unidadMedida}' no existe en este establecimiento.";
                } else {
                    $unidadMedidaId = $catalogos['unidades'][$key];
                }
            }

            // es_servicio: aceptamos SI/NO, true/false, 1/0, vacio = NO
            $esServicioBool = $this->parsearBooleano($esServicio);
            if ($esServicioBool === null) {
                $erroresFila[] = "El campo es_servicio debe ser SI o NO.";
                $esServicioBool = false;
            }

            // numericos
            if (!$this->esNumeroValido($precioVenta) || (float) $precioVenta < 0) {
                $erroresFila[] = 'El precio_venta debe ser un numero mayor o igual a 0.';
            }

            if ($precioCompra !== null && $precioCompra !== '') {
                if (!$this->esNumeroValido($precioCompra) || (float) $precioCompra < 0) {
                    $erroresFila[] = 'El precio_compra debe ser un numero mayor o igual a 0.';
                }
            }

            // stock: si es servicio se fuerza a 0 ignorando lo que venga
            // si no es servicio debe ser entero >= 0
            if (!$esServicioBool) {
                if ($stock === null || $stock === '' || !$this->esEnteroValido($stock) || (int) $stock < 0) {
                    $erroresFila[] = 'El stock debe ser un entero mayor o igual a 0.';
                }
            }

            // iva opcional, si viene debe ser numerico entre 0 y 100
            if ($iva !== null && $iva !== '') {
                if (!$this->esNumeroValido($iva) || (float) $iva < 0 || (float) $iva > 100) {
                    $erroresFila[] = 'El iva debe ser un numero entre 0 y 100.';
                }
            }

            // codigo: si viene se valida unicidad contra BD y contra archivo
            if (!empty($codigo)) {
                $codigoUpper = strtoupper($codigo);
                if (isset($catalogos['codigos_existentes'][$codigoUpper])) {
                    $erroresFila[] = "El codigo '{$codigo}' ya existe en la base de datos.";
                } elseif (isset($codigosArchivo[$codigoUpper])) {
                    $erroresFila[] = "El codigo '{$codigo}' esta duplicado dentro del archivo (fila " . $codigosArchivo[$codigoUpper] . ').';
                } else {
                    // lo registramos para detectar duplicados de filas posteriores
                    $codigosArchivo[$codigoUpper] = $numeroFila;
                }
            }

            // clave: misma logica que codigo
            if (!empty($clave)) {
                $claveUpper = strtoupper($clave);
                if (isset($catalogos['claves_existentes'][$claveUpper])) {
                    $erroresFila[] = "La clave '{$clave}' ya existe en la base de datos.";
                } elseif (isset($clavesArchivo[$claveUpper])) {
                    $erroresFila[] = "La clave '{$clave}' esta duplicada dentro del archivo (fila " . $clavesArchivo[$claveUpper] . ').';
                } else {
                    $clavesArchivo[$claveUpper] = $numeroFila;
                }
            }

            if (!empty($erroresFila)) {
                $errores[] = [
                    'fila'    => $numeroFila,
                    'errores' => $erroresFila,
                ];
                // Construimos la fila para el archivo de errores con los valores originales
                $filasFallidas[] = [
                    $codigo,
                    $clave,
                    $nombre,
                    $categoria,
                    $unidadMedida,
                    $stock,
                    $precioCompra,
                    $precioVenta,
                    $esServicio,
                    $iva,
                    $descripcion,
                    implode(' | ', $erroresFila),
                ];
                continue;
            }

            // Fila valida, la dejamos lista para insertar
            $filasValidas[] = [
                'fila'             => $numeroFila,
                'codigo'           => $codigo ?: null,
                'clave'            => $clave ?: null,
                'nombre'           => $nombre,
                'categoria_id'     => $categoriaId,
                'unidad_medida_id' => $unidadMedidaId,
                'stock'            => $esServicioBool ? 0 : (int) $stock,
                'precio_compra'    => ($precioCompra !== null && $precioCompra !== '') ? (float) $precioCompra : null,
                'precio_venta'     => (float) $precioVenta,
                'es_servicio'      => $esServicioBool,
                'iva'              => ($iva !== null && $iva !== '') ? (float) $iva : null,
                'descripcion'      => $descripcion ?: null,
            ];
        }

        return [
            'validas'        => count($filasValidas),
            'con_errores'    => count($filasFallidas),
            'errores'        => $errores,
            'filas_validas'  => $filasValidas,
            'filas_fallidas' => $filasFallidas,
        ];
    }

    /**
     * Inserta las filas validas en lotes usando insert() de Eloquent.
     * Las filas que necesiten codigo autogenerado lo reciben antes del insert.
     * Devuelve la cantidad total insertada.
     */
    private function insertarPorLotes(array $filasValidas, array $catalogos, int $establecimientoId): int
    {
        // Catalogo en memoria de codigos/claves que se van generando
        // para evitar colisiones dentro del mismo lote
        $codigosUsados = $catalogos['codigos_existentes'];
        $clavesUsadas  = $catalogos['claves_existentes'];

        // Necesitamos los nombres de categoria para autogenerar codigos
        // los obtenemos una sola vez
        $categoriaNombrePorId = Category::where('establecimiento_id', $establecimientoId)
            ->pluck('nombre', 'id')
            ->toArray();

        $now = now();
        $registros = [];

        foreach ($filasValidas as $fila) {
            // Si no trae codigo lo autogeneramos
            if (empty($fila['codigo'])) {
                $codigoGenerado = $this->generarCodigoUnico(
                    $categoriaNombrePorId[$fila['categoria_id']] ?? '',
                    $fila['nombre'],
                    $codigosUsados
                );
                $fila['codigo'] = $codigoGenerado;
                $codigosUsados[strtoupper($codigoGenerado)] = true;
            }

            // Si no trae clave usamos el mismo valor del codigo (mismo criterio que el modulo manual)
            if (empty($fila['clave'])) {
                $fila['clave'] = $fila['codigo'];
                $clavesUsadas[strtoupper($fila['codigo'])] = true;
            }

            $registros[] = [
                'establecimiento_id' => $establecimientoId,
                'categoria_id'       => $fila['categoria_id'],
                'unidad_medida_id'   => $fila['unidad_medida_id'],
                'nombre'             => $fila['nombre'],
                'codigo'             => $fila['codigo'],
                'clave'              => $fila['clave'],
                'descripcion'        => $fila['descripcion'],
                'precio_compra'      => $fila['precio_compra'],
                'precio_venta'       => $fila['precio_venta'],
                'iva'                => $fila['iva'],
                'es_servicio'        => $fila['es_servicio'],
                'stock'              => $fila['stock'],
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        // Insertamos en lotes de 200 para no saturar memoria con archivos grandes
        $tamanoLote = 200;
        $total = 0;
        foreach (array_chunk($registros, $tamanoLote) as $lote) {
            Products::insert($lote);
            $total += count($lote);
        }

        return $total;
    }

    /**
     * Genera un codigo unico con formato XX-YYY-ZZZZZ
     * basado en categoria, nombre y aleatorio, evitando colisiones
     * tanto con BD como con codigos generados en este mismo lote.
     */
    private function generarCodigoUnico(string $nombreCategoria, string $nombreProducto, array $codigosUsados): string
    {
        $prefijoCategoria = strtoupper(substr($this->limpiarParaCodigo($nombreCategoria), 0, 2));
        $prefijoNombre    = strtoupper(substr($this->limpiarParaCodigo($nombreProducto), 0, 3));
        $prefijo = $prefijoCategoria . '-' . $prefijoNombre . '-';

        $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxIntentos = 15;

        for ($i = 0; $i < $maxIntentos; $i++) {
            $aleatorio = '';
            for ($j = 0; $j < 5; $j++) {
                $aleatorio .= $caracteres[random_int(0, strlen($caracteres) - 1)];
            }
            $candidato = $prefijo . $aleatorio;
            if (!isset($codigosUsados[strtoupper($candidato)])) {
                return $candidato;
            }
        }

        throw new Exception('No fue posible generar un codigo unico para uno de los productos importados.');
    }

    private function limpiarParaCodigo(string $texto): string
    {
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        return preg_replace('/[^A-Za-z]/', '', $texto);
    }

    /**
     * Guarda el archivo subido en storage/app/imports/{est}/{token}.xlsx
     * y devuelve el token generado.
     */
    private function guardarArchivoTemporal(UploadedFile $file, int $establecimientoId): string
    {
        $token = (string) Str::uuid();
        $ruta  = $this->rutaArchivoTemporal($token, $establecimientoId);

        Storage::disk('local')->putFileAs(
            dirname($ruta),
            $file,
            basename($ruta)
        );

        return $token;
    }

    private function rutaArchivoTemporal(string $token, int $establecimientoId): string
    {
        // Sanitizamos por seguridad: solo permitimos caracteres validos de uuid
        $tokenLimpio = preg_replace('/[^A-Za-z0-9\-]/', '', $token);
        return "imports/{$establecimientoId}/{$tokenLimpio}.xlsx";
    }

    /**
     * Elimina archivos temporales mas viejos que el TTL configurado
     * para evitar acumulacion si el usuario nunca confirma la importacion.
     */
    private function limpiarArchivosVencidos(int $establecimientoId): void
    {
        $directorio = "imports/{$establecimientoId}";
        if (!Storage::disk('local')->exists($directorio)) {
            return;
        }

        $umbral = now()->subMinutes($this->ttlMinutos)->timestamp;
        $archivos = Storage::disk('local')->files($directorio);

        foreach ($archivos as $archivo) {
            $modificado = Storage::disk('local')->lastModified($archivo);
            if ($modificado < $umbral) {
                Storage::disk('local')->delete($archivo);
            }
        }
    }

    // ============== Helpers de validacion y normalizacion ==============

    private function limpiar($valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $str = trim((string) $valor);
        return $str === '' ? null : $str;
    }

    private function normalizar(?string $valor): string
    {
        if ($valor === null) {
            return '';
        }
        // Comparacion case-insensitive y sin acentos para resolver categoria/unidad
        $sinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
        return strtolower(trim($sinAcentos));
    }

    private function esNumeroValido($valor): bool
    {
        return is_numeric($valor);
    }

    private function esEnteroValido($valor): bool
    {
        if (!is_numeric($valor)) {
            return false;
        }
        return floor((float) $valor) == (float) $valor;
    }

    /**
     * Parsea un valor a booleano. Acepta SI/NO, true/false, 1/0, S/N.
     * Devuelve null si no se puede interpretar (lo cual genera error de validacion).
     * Vacio se considera NO.
     */
    private function parsearBooleano(?string $valor): ?bool
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        $normalizado = strtolower($this->normalizar($valor));

        if (in_array($normalizado, ['si', 's', '1', 'true', 'verdadero', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($normalizado, ['no', 'n', '0', 'false', 'falso'], true)) {
            return false;
        }

        return null;
    }
}