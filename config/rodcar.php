<?php

return [
    'clasificacion' => [
        'umbral_confianza_ia'     => (int) env('MOVS_CLASIFICACION_UMBRAL_CONFIANZA', 90),
        'umbral_similitud_trgm'   => (float) env('MOVS_CLASIFICACION_UMBRAL_SIMILITUD', 0.6),
        'modelo'                  => env('MOVS_CLASIFICACION_MODEL', 'claude-haiku-4-5-20251001'),
        'num_ejemplos_few_shot'   => (int) env('MOVS_CLASIFICACION_NUM_EJEMPLOS', 8),
    ],
];
