<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchContextController extends Controller
{
    public function select(Request $request, Branch $branch): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole('super admin'), 403);
        abort_unless((int) $branch->tenant_id === (int) $user->tenant_id, 403);

        session(['active_branch_id' => $branch->id]);

        return $this->redirectAfterContextChange(
            $request,
            'dashboard',
            'تم اختيار الفرع: '.$branch->name
        );
    }

    public function clear(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole('super admin'), 403);
        session()->forget('active_branch_id');

        return $this->redirectAfterContextChange(
            $request,
            'dashboard',
            'عدت إلى العرض المركزي لجميع الفروع.'
        );
    }

    private function redirectAfterContextChange(Request $request, string $defaultRoute, string $message): RedirectResponse
    {
        $redirect = $request->input('redirect');
        if (is_string($redirect) && $this->isSafeInternalRedirect($request, $redirect)) {
            return redirect()->to($redirect)->with('success', $message);
        }

        return redirect()->route($defaultRoute)->with('success', $message);
    }

    private function isSafeInternalRedirect(Request $request, string $redirect): bool
    {
        if ($redirect === '' || str_starts_with($redirect, '//')) {
            return false;
        }

        if (str_starts_with($redirect, '/')) {
            return true;
        }

        if (! str_contains($redirect, '://')) {
            return false;
        }

        $redirectHost = parse_url($redirect, PHP_URL_HOST);

        return is_string($redirectHost)
            && strcasecmp($redirectHost, $request->getHost()) === 0;
    }
}
