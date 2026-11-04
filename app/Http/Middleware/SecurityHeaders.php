<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Adds defensive HTTP headers to every response (OWASP A05). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $dev = $this->viteDevOrigin();
        $devScript = $dev ? " {$dev}" : '';
        $devConnect = $dev ? " {$dev} ".str_replace(['http://', 'https://'], 'ws://', $dev) : '';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'{$devScript}",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com{$devScript}",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data:",
            "connect-src 'self'{$devConnect}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        // Authenticated pages must never be stored by shared caches or the back/forward cache of a kiosk PC.
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /** While `npm run dev` is running, Vite publishes its origin in public/hot. */
    private function viteDevOrigin(): ?string
    {
        if (app()->environment('production') || ! is_file(public_path('hot'))) {
            return null;
        }
        $url = trim((string) @file_get_contents(public_path('hot')));
        if (! preg_match('#^https?://[\w.\-\[\]:]+$#', $url)) {
            return null;
        }

        return $url;
    }
}
