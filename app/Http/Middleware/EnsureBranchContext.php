<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchContext
{
    /**
     * يفرض وجود فرع نشط للشاشات التشغيلية لكل فرع (كاشير، موظفين، ورديات، مجموعات حضور، فواتير اليوم).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->hasRole('super admin')) {
            if (! $user->branch_id) {
                return redirect()->route('dashboard')
                    ->with('error', 'لا يوجد فرع معيّن لحسابك. تواصل مع المشرف.');
            }

            return $next($request);
        }

        if (! BranchContext::hasBranch()) {
            session()->forget('active_branch_id');

            return redirect()->route('dashboard')
                ->with('error', 'اختر فرعاً من لوحة التحكم للدخول إلى هذا القسم.');
        }

        return $next($request);
    }
}
