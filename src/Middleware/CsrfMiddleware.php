<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use App\Security\Csrf;

class CsrfMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $method = strtoupper($request->getMethod());

        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $parsedBody = (array)$request->getParsedBody();
            $token = $parsedBody['_csrf_token'] ?? $request->getHeaderLine('X-CSRF-Token');

            if (!Csrf::validate($token)) {
                $response = new SlimResponse();
                $response->getBody()->write(json_encode([
                    'error' => 'CSRF verification failed. Please refresh the page and try again.'
                ], JSON_UNESCAPED_UNICODE));
                
                return $response
                    ->withStatus(403)
                    ->withHeader('Content-Type', 'application/json');
            }
        }

        return $handler->handle($request);
    }
}
