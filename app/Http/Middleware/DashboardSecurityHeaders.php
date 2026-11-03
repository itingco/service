<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DashboardSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Business Intelligence memuat halaman analisis melalui iframe pada origin
        // yang sama. DENY memblokir iframe tersebut (ikon broken page di browser).
        // Tetap tolak framing untuk halaman lain, termasuk login dan pengaturan.
        $isInternalAnalysisPage = $request->isMethod('GET')
            && (
                $request->is('business-analytics')
                || $request->is('business-analytics/product-advisor')
                || $request->is('sales-insights')
            );

        $response->headers->set(
            'X-Frame-Options',
            $isInternalAnalysisPage ? 'SAMEORIGIN' : 'DENY'
        );
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cache-Control', 'no-store, private');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
