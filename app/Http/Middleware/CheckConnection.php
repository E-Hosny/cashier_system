<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\OfflineService;
use Symfony\Component\HttpFoundation\Response;

class CheckConnection
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // التحقق من حالة الاتصال
        $isOnline = OfflineService::isOnline();
        
        // إضافة حالة الاتصال إلى البيانات المرسلة للواجهة
        if ($request->expectsJson()) {
            $response = $next($request);
            
            // إضافة headers لحالة الاتصال
            $response->headers->set('X-Connection-Status', $isOnline ? 'online' : 'offline');
            
            return $response;
        }
        
        return $next($request);
    }
} 