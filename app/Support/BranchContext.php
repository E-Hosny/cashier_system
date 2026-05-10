<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Facades\Auth;

class BranchContext
{
    /**
     * معرف الفرع النشط للعمليات المقيّدة بالفرع.
     * للسوبر أدمن يُؤخذ من الجلسة؛ للباقي من عمود branch_id على المستخدم.
     */
    public static function id(): ?int
    {
        $user = Auth::user();
        if (! $user || ! $user->tenant_id) {
            return null;
        }

        if ($user->hasRole('super admin')) {
            $sessionId = session('active_branch_id');
            if (! $sessionId) {
                return null;
            }
            $exists = Branch::withoutGlobalScopes()
                ->where('id', $sessionId)
                ->where('tenant_id', $user->tenant_id)
                ->exists();

            return $exists ? (int) $sessionId : null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }

    public static function requireId(): int
    {
        $id = self::id();
        if ($id === null) {
            abort(403, 'لم يتم تحديد فرع نشط.');
        }

        return $id;
    }

    public static function hasBranch(): bool
    {
        return self::id() !== null;
    }
}
