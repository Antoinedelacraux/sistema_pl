<?php

return [

    /*
    | Notificación por correo al crear un ingreso (Registro).
    | .env: REGISTRO_INGRESO_MAIL_ENABLED=true
    |       REGISTRO_INGRESO_MAIL_TO=uno@empresa.com,otro@empresa.com
    |       MAIL_* (smtp, etc.)
    */
    'ingreso' => [
        'notify_enabled' => filter_var(env('REGISTRO_INGRESO_MAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

        'notify_to' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('REGISTRO_INGRESO_MAIL_TO', ''))
        ))),

        'skip_ajuste_temporal' => filter_var(env('REGISTRO_INGRESO_MAIL_SKIP_AJUSTE', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
