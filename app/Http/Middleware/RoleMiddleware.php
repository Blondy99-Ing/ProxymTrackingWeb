<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth('web')->user(); // Employe connecté

        if (!$user || !$user->role) {
            abort(403, 'Accès refusé.');
        }

        if (!in_array($user->role->slug, $roles, true)) {
            abort(403, 'Accès refusé.');
        }

        return $next($request);
    }
}
