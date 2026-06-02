<?php

// Aceptamos varios orígenes separados por coma vía FRONTEND_URLS para que
// convivan el Vite dev (:5173) y el preview de producción (:4173).
$origenes = env(
    'FRONTEND_URLS',
    'http://localhost:5173,http://localhost:4173,http://127.0.0.1:4173',
);

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', $origenes)))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
