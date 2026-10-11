<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Config\Database;
use App\Security\Csrf;
use App\Security\AuditLogger;

class ExchangeRateController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function update(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $rate = (int)($body['rate_usd_to_iqd'] ?? 0);

        if ($rate <= 0) {
            return $response->withStatus(302)->withHeader('Location', '/inventory?error=Invalid+exchange+rate');
        }

        $pdo = Database::getAppPdo();
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO exchange_rates (rate_usd_to_iqd, set_by_user_id, effective_date)
            VALUES (:rate, :user_id, :now)
        ");
        $stmt->execute([
            'rate' => $rate,
            'user_id' => $user['id'],
            'now' => $now
        ]);

        AuditLogger::log('EXCHANGE_RATE', (int)$pdo->lastInsertId(), 'SET_RATE', null, ['rate' => $rate], (int)$user['id']);

        $referer = $request->getHeaderLine('Referer') ?: '/inventory';
        return $response->withStatus(302)->withHeader('Location', $referer . '?success=rate_updated');
    }
}
