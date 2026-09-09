<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictHrToEmployees
{
    /**
     * مسؤول الموظفين يصل فقط للوحة التحكم وصفحة الموظفين والملف الشخصي.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isHrOnly()) {
            return $next($request);
        }

        if ($request->routeIs([
            'dashboard',
            'admin.employees.*',
            'profile.show',
            'user-profile-information.update',
            'user-password.update',
            'current-user.destroy',
            'current-user-photo.destroy',
            'password.confirm',
            'password.confirmation',
            'two-factor.*',
            'logout',
        ])) {
            return $next($request);
        }

        return redirect()->route('admin.employees.index')
            ->with('error', 'هذا الحساب مخصص لمتابعة الموظفين فقط.');
    }
}
