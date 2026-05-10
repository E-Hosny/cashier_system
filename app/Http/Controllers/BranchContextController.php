<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class BranchContextController extends Controller
{
    public function select(Branch $branch): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole('super admin'), 403);
        abort_unless((int) $branch->tenant_id === (int) $user->tenant_id, 403);

        session(['active_branch_id' => $branch->id]);

        return redirect()->route('dashboard')->with('success', 'تم اختيار الفرع: '.$branch->name);
    }

    public function clear(): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole('super admin'), 403);
        session()->forget('active_branch_id');

        return redirect()->route('dashboard')->with('success', 'عدت إلى العرض المركزي لجميع الفروع.');
    }
}
