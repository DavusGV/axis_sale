<?php

namespace App\Http\Controllers;

use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class UnidadesMedidasController extends Controller
{
    // lista todas las unidades del establecimiento activo
    public function index(Request $request)
    {
        try {
            $establecimiento_id = app('establishment_id');

            $query = UnidadMedida::where('establecimiento_id', $establecimiento_id);

            // filtro de busqueda opcional
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('unidad', 'like', "%{$search}%")
                      ->orWhere('abreviatura', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 15);
            $paginator = $query->orderBy('unidad')->paginate($perPage);

            return response()->json([
                'data'         => $paginator->items(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ], 200);

        } catch (Exception $e) {
            return $this->InternalError([
                'error'   => 'Error al obtener unidades de medida',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // crea una nueva unidad de medida
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $establecimiento_id = app('establishment_id');

            $data = $request->validate([
                'unidad'      => 'required|string|max:100',
                'abreviatura' => 'required|string|max:20',
                'descripcion' => 'nullable|string',
            ]);

            // unicidad por establecimiento
            $existe = UnidadMedida::where('establecimiento_id', $establecimiento_id)
                ->where('unidad', $data['unidad'])
                ->exists();

            if ($existe) {
                return $this->BadRequest([
                    'error' => 'Ya existe una unidad en este establecimiento.',
                ]);
            }

            $data['establecimiento_id'] = $establecimiento_id;
            $unidad = UnidadMedida::create($data);

            DB::commit();
            return $this->Success($unidad);

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest([
                'error'    => 'Validation failed',
                'messages' => $e->errors(),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError([
                'error'   => 'Error al crear unidad de medida',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // actualiza unidad, abreviatura o descripcion
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $establecimiento_id = app('establishment_id');

            $unidad = UnidadMedida::where('id', $id)
                ->where('establecimiento_id', $establecimiento_id)
                ->firstOrFail();

            $data = $request->validate([
                'unidad'      => 'sometimes|string|max:100',
                'abreviatura' => 'sometimes|string|max:20',
                'descripcion' => 'nullable|string',
            ]);

            $unidad->update($data);

            DB::commit();
            return $this->Success($unidad);

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest([
                'error'    => 'Validation failed',
                'messages' => $e->errors(),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError([
                'error'   => 'Error al actualizar unidad de medida',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // elimina solo si ningun producto la esta usando
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $establecimiento_id = app('establishment_id');

            $unidad = UnidadMedida::where('id', $id)
                ->where('establecimiento_id', $establecimiento_id)
                ->firstOrFail();

            // bloquear eliminacion si hay productos vinculados
            $enUso = $unidad->productos()->exists();

            if ($enUso) {
                return $this->BadRequest([
                    'error' => 'No se puede eliminar: hay productos usando esta unidad de medida.',
                ]);
            }

            $unidad->delete();

            DB::commit();
            return $this->Success(['message' => 'Unidad de medida eliminada correctamente.']);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError([
                'error'   => 'Error al eliminar unidad de medida',
                'message' => $e->getMessage(),
            ]);
        }
    }
}