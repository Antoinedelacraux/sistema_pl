<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Power Automate · subida de PDF a OneDrive
    |--------------------------------------------------------------------------
    | Flujo recomendado:
    | 1. Trigger: "When an HTTP request is received"
    | 2. Schema JSON del trigger (ver comentario al final de este archivo)
    | 3. Acción: Create file (OneDrive) con base64ToBinary(body('contenido_base64'))
    | 4. Acción: Create share link (OneDrive) — File = Id del paso anterior, tipo View
    | 5. Response: devolver id/name de Crear archivo y webUrl del vínculo compartido
    |
    | POWER_AUTOMATE_UPLOAD_URL = URL completa del trigger HTTP (copiar del Flow)
    */
    'enabled' => filter_var(env('POWER_AUTOMATE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'upload_url' => env('POWER_AUTOMATE_UPLOAD_URL'),

    'api_key' => env('POWER_AUTOMATE_API_KEY'),

    'timeout_seconds' => (int) env('POWER_AUTOMATE_TIMEOUT', 25),

    'max_upload_kb' => (int) env('POWER_AUTOMATE_MAX_UPLOAD_KB', 10240),

    /*
    | Ruta al bundle de certificados CA (cacert.pem). En Windows/local suele faltar
    | en php.ini; este archivo evita "cURL error 60: unable to get local issuer certificate".
    */
    'ca_bundle' => env('POWER_AUTOMATE_CA_BUNDLE', storage_path('certs/cacert.pem')),

    'verify_ssl' => filter_var(env('POWER_AUTOMATE_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),

];

/*
Schema del trigger HTTP en Power Automate:

{
    "type": "object",
    "properties": {
        "factura_guia": { "type": "string" },
        "tipo": { "type": "string" },
        "nombre_archivo": { "type": "string" },
        "contenido_base64": { "type": "string" }
    }
}

Respuesta esperada del Flow (acción Response, código 200):

IMPORTANTE: «Crear archivo» NO devuelve WebUrl. Use «Crear vínculo para compartir» (Create share link).

Pasos del flujo:
1. Crear archivo → carpeta System PL/Ingresos|Egresos|Traslado, nombre y base64
2. Crear vínculo para compartir → Archivo = Id de Crear archivo, Tipo = view (Ver)
3. Respuesta HTTP 200:

{
    "id": "@{outputs('Crear_archivo')?['body/Id']}",
    "webUrl": "@{outputs('Crear_vínculo_para_compartir')?['body/WebUrl']}",
    "name": "@{outputs('Crear_archivo')?['body/Name']}"
}

(Ajuste los nombres Crear_archivo y Crear_vínculo_para_compartir al nombre exacto de cada paso en su flujo.)

Carpeta sugerida en Create file:
/Planeamiento/Movimientos/@{triggerBody()?['tipo']}/@{formatDateTime(utcNow(), 'yyyy/MM')}/@{triggerBody()?['nombre_archivo']}

Valores de tipo desde Laravel: ingreso, egreso, traslado
*/
