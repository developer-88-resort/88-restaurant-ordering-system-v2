<?php

namespace App\Http\Middleware;

use App\Enums\Department;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `department:restaurant` — only that department's Admin/Staff (and every
 * Superadmin) get through. See App\Enums\Department.
 */
class EnsureUserInDepartment
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $department): Response
    {
        $user = $request->user();

        abort_unless($user && $user->worksIn(Department::from($department)), 403);

        return $next($request);
    }
}
