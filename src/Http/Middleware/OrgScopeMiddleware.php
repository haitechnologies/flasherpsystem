<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Container;
use App\Http\Request;
use App\Http\Response;

class OrgScopeMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if (function_exists('dashboardRequireActiveOrganization')) {
            $orgId = dashboardRequireActiveOrganization();
        } else {
            $projectPre = defined('PROJECT_PREFIX') ? PROJECT_PREFIX : 'flasherpsystem';
            $orgId = (int)($_SESSION[$projectPre]['DASHBOARD']['organization_id'] ?? 0);
        }
        $container = Container::getInstance();
        $container->set('active_organization_id', $orgId);

        return $next($request);
    }
}
