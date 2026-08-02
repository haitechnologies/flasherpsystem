<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    private string $sessionKey;

    public function __construct(string $sessionKey = 'flasherpsystem')
    {
        $this->sessionKey = $sessionKey;
    }

    public function process(Request $request, callable $next): Response
    {
        $userId = $_SESSION[$this->sessionKey]['DASHBOARD']['user_id'] ?? null;
        if ($userId === null) {
            return Response::redirect('../login.php');
        }

        return $next($request);
    }
}
