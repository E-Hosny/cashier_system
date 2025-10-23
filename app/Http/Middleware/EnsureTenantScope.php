<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantScope
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // التحقق من وجود مستخدم مسجل دخول
        if (auth()->check()) {
            $user = auth()->user();
            
            // التحقق من وجود tenant_id للمستخدم
            if (!$user->tenant_id) {
                // إذا لم يكن لديه tenant_id، نعينه تلقائياً
                $user->update(['tenant_id' => $user->id]);
            }
            
            // يمكن إضافة المزيد من التحققات هنا إذا لزم الأمر
            // مثل التحقق من صلاحيات الوصول للـ tenant
        }
        
        return $next($request);
    }
}

