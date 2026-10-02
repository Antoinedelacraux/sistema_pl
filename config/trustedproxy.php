<?php

/*
|--------------------------------------------------------------------------
| Proxies de confianza
|--------------------------------------------------------------------------
|
| Solo se aceptan cabeceras X-Forwarded-* (IP real del cliente, esquema,
| host) cuando la conexión llega desde uno de estos proxies. Así nadie puede
| falsear su IP con X-Forwarded-For para evadir el rate limit de login.
|
| La app se publica con Cloudflare Tunnel: cloudflared corre en el mismo
| servidor y conecta a PHP desde 127.0.0.1 / ::1 (valor por defecto).
|
| TRUSTED_PROXIES: lista separada por comas de IPs o rangos CIDR.
| TRUSTED_PROXIES_CLOUDFLARE=true: añade los rangos públicos de Cloudflare
|   (solo si el servidor recibe tráfico directo desde la red de Cloudflare,
|   p. ej. proxy naranja sin túnel). Fuente: https://www.cloudflare.com/ips/
|
| Nunca usar "*" en producción.
|
*/

$proxies = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))
), fn ($ip) => $ip !== '' && $ip !== '*' && $ip !== '**'));

if (filter_var(env('TRUSTED_PROXIES_CLOUDFLARE', false), FILTER_VALIDATE_BOOLEAN)) {
    $proxies = array_merge($proxies, [
        // IPv4 (cloudflare.com/ips-v4, revisado 2026-10)
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        // IPv6 (cloudflare.com/ips-v6)
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ]);
}

return [

    // Leído por Illuminate\Http\Middleware\TrustProxies (compatible con config:cache).
    'proxies' => array_values(array_unique($proxies)),

];
