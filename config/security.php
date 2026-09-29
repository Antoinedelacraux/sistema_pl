<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy (solo Report-Only)
    |--------------------------------------------------------------------------
    | Si es true, SecurityHeaders envía Content-Security-Policy-Report-Only
    | (no bloquea nada). Se lee con config() para que funcione con config:cache.
    */
    'csp_report_only' => filter_var(env('SECURITY_CSP_REPORT_ONLY', false), FILTER_VALIDATE_BOOLEAN),

];
