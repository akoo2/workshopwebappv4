<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        // Strict Content Security Policy allowing Tailwind / CDN / Chart.js / local scripts
        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
             . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
             . "font-src 'self' https://fonts.gstatic.com; "
             . "img-src 'self' data: blob:; "
             . "connect-src 'self'; "
             . "frame-ancestors 'none'; "
             . "base-uri 'self'; "
             . "form-action 'self';";

        $response = $response
            ->withHeader('Content-Security-Policy', $csp)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Add HSTS if request is HTTPS
        $uri = $request->getUri();
        if ($uri->getScheme() === 'https' || ($request->getHeaderLine('X-Forwarded-Proto') === 'https')) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
