<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeAttendanceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $allowed = $user
            && (
                $user->can('employees.attendance')
                || $user->can('employees.view')
                || $user->can('manage employee attendance')
            );

        if (! $allowed) {
            abort(403, 'غير مصرح لك بالوصول لصفحة إدارة الحضور والانصراف');
        }

        return $next($request);
    }
} 