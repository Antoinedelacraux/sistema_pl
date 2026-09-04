<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defensive HTTP security headers without breaking Blade/Vite inline needs.
 * Strict CSP is not enforced (see qa-report/NOTES.md); Report-Only is optional via env.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-DNS-Prefetch-Control', 'on');

        // Hide framework fingerprint (Symfony + PHP SAPI)
        $response->headers->remove('X-Powered-By');
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }
        @ini_set('expose_php', '0');

        // Optional CSP Report-Only (does not block). Enable with SECURITY_CSP_REPORT_ONLY=true
        if (filter_var(env('SECURITY_CSP_REPORT_ONLY', false), FILTER_VALIDATE_BOOLEAN)) {
            $csp = implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "img-src 'self' data: blob:",
                "font-src 'self' https://fonts.gstatic.com data:",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "script-src 'self' 'unsafe-inline'",
                "connect-src 'self' ws: wss:",
                "object-src 'none'",
            ]);
            $response->headers->set('Content-Security-Policy-Report-Only', $csp);
        }

        // Selective Cache-Control: never cache authenticated HTML; keep guest HTML fresh (CSRF)
        if ($request->isMethod('GET') && $this->isHtmlResponse($response)) {
            if ($request->user()) {
                $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
                $response->headers->set('Pragma', 'no-cache');
            } else {
                $response->headers->set('Cache-Control', 'private, no-cache, must-revalidate');
            }
        }

        return $response;
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains($contentType, 'text/html');
    }
}