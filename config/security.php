<?php

return [

    'csp_report_only' => filter_var(env('SECURITY_CSP_REPORT_ONLY', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | HSTS (solo respuestas HTTPS). Recomendado en producción detrás de TLS.
    */
    'hsts' => filter_var(env('SECURITY_HSTS', true), FILTER_VALIDATE_BOOLEAN),
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31_536_000),

    'rate_limit' => [
        'login_per_minute' => (int) env('RATE_LIMIT_LOGIN', 5),
        'web_per_minute' => (int) env('RATE_LIMIT_WEB', 120),
        'imports_per_minute' => (int) env('RATE_LIMIT_IMPORTS', 10),
        'destructive_per_minute' => (int) env('RATE_LIMIT_DESTRUCTIVE', 8),
    ],

];
