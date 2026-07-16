<?php

// Configuracion de la rejilla de etiquetas para impresion en hoja carta.
// Ajusta estos valores probando con tu impresora hasta que calce el papel.
return [
    'columnas' => 4,
    'filas'    => 10,

    'guias_corte' => true,

    'pagina' => [
        'formato'     => 'letter',   // letter | a4
        'orientacion' => 'portrait', // portrait | landscape
    ],

    // margenes de la hoja en milimetros
    'margen' => [
        'superior'  => 10,
        'inferior'  => 10,
        'izquierdo' => 8,
        'derecho'   => 8,
    ],

    'etiqueta' => [
        'alto'           => 25, // alto de cada celda en mm
        'gap_horizontal' => 2,
        'gap_vertical'   => 2,
        'alto_barcode'   => 12, // alto de la imagen del barcode en mm
    ],
];