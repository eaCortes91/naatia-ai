<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAdminKey
{
    public function handle(Request $request, Closure $next)
    {
        $adminKey = trim((string) env('ADMIN_PANEL_KEY', ''));

        if ($adminKey !== '') {
            $provided = (string) ($request->header('X-Admin-Key') ?? $request->query('admin_key') ?? '');

            if (! hash_equals($adminKey, $provided)) {
                abort(403, 'Admin key invalid.');
            }
        }

        return $next($request);
    }
}
