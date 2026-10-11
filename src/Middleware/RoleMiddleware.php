<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use Slim\Views\Twig;
use App\Lang\Translator;

class RoleMiddleware implements MiddlewareInterface
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user');

        if (!$user || !in_array($user['role'] ?? '', $this->allowedRoles, true)) {
            $response = new SlimResponse();

            // If request expects JSON (API or AJAX)
            $accept = $request->getHeaderLine('Accept');
            if (str_contains($accept, 'application/json')) {
                $response->getBody()->write(json_encode([
                    'error' => Translator::trans('forbidden')
                ], JSON_UNESCAPED_UNICODE));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Render 403 Forbidden page
            $response->getBody()->write("
                <!DOCTYPE html>
                <html lang=\"en\" dir=\"" . (Translator::isRtl() ? "rtl" : "ltr") . "\">
                <head>
                    <meta charset=\"UTF-8\">
                    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                    <title>403 - " . htmlspecialchars(Translator::trans('forbidden')) . "</title>
                    <script src=\"https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4\"></script>
                </head>
                <body class=\"bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4 font-sans\">
                    <div class=\"bg-white rounded-xl shadow-sm border border-slate-200 p-6 max-w-md w-full text-center\">
                        <div class=\"w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4\">
                            <svg class=\"w-6 h-6\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z\"/></svg>
                        </div>
                        <h1 class=\"text-xl font-bold text-slate-900 mb-2\">" . htmlspecialchars(Translator::trans('forbidden')) . "</h1>
                        <p class=\"text-sm text-slate-500 mb-6\">Your current role does not have access to this section.</p>
                        <a href=\"/\" class=\"inline-block w-full py-2.5 px-4 bg-slate-900 text-white rounded-lg font-medium text-sm hover:bg-slate-800 transition\">Return to Dashboard</a>
                    </div>
                </body>
                </html>
            ");
            return $response->withStatus(403)->withHeader('Content-Type', 'text/html; charset=UTF-8');
        }

        return $handler->handle($request);
    }
}
