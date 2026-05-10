<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;

        $users = User::with(['roles', 'branch'])
            ->where('tenant_id', $tenantId)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'branch_name' => $user->branch?->name,
                    'created_at' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : '',
                ];
            });

        $roles = Role::all()->pluck('name');

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function create()
    {
        $roles = Role::all()->pluck('name');
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;

        if ($request->input('branch_id') === '' || $request->input('branch_id') === null) {
            $request->merge(['branch_id' => null]);
        }

        $isSuperTarget = collect($request->roles)->contains(fn ($r) => $r === 'super admin');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
            'branch_id' => [
                Rule::requiredIf(! $isSuperTarget),
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $tenantId,
            'branch_id' => $request->branch_id,
        ]);

        $user->assignRole($request->roles);

        return redirect()->route('admin.users.index')
            ->with('message', 'تم إنشاء المستخدم بنجاح');
    }

    public function edit(User $user)
    {
        if ($user->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        $roles = Role::all()->pluck('name');
        $userRoles = $user->roles->pluck('name');
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $userRoles,
                'branch_id' => $user->branch_id,
            ],
            'roles' => $roles,
            'branches' => $branches,
        ]);
    }

    public function update(Request $request, User $user)
    {
        if ($user->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        $tenantId = Auth::user()->tenant_id;

        if ($request->input('branch_id') === '' || $request->input('branch_id') === null) {
            $request->merge(['branch_id' => null]);
        }

        $isSuperTarget = collect($request->roles)->contains(fn ($r) => $r === 'super admin');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
            'password' => 'nullable|confirmed|min:8',
            'branch_id' => [
                Rule::requiredIf(! $isSuperTarget),
                'nullable',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'branch_id' => $request->branch_id,
        ]);

        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        $user->syncRoles($request->roles);

        return redirect()->route('admin.users.index')
            ->with('message', 'تم تحديث المستخدم بنجاح');
    }

    public function destroy(User $user)
    {
        if ($user->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        if ($user->id === Auth::id()) {
            return back()->with('error', 'لا يمكنك حذف حسابك الحالي');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('message', 'تم حذف المستخدم بنجاح');
    }

    public function resetPassword(Request $request, User $user)
    {
        if ($user->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('message', 'تم إعادة تعيين كلمة المرور بنجاح');
    }
}
