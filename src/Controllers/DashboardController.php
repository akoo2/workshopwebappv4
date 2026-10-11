<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Config\Database;
use App\Security\Csrf;

class DashboardController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $pdo = Database::getAppPdo();

        // Check if database tables exist
        $totalStaff = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $totalStaff = (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {}

        return $this->view->render($response, 'dashboard/index.twig', [
            'user' => $user,
            'total_staff' => $totalStaff,
            'csrf_token' => Csrf::getToken()
        ]);
    }
}
