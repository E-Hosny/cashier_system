<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
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
        
        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => Auth::user()->tenant_id,
        ]);

        $user->assignRole($request->roles);

        return redirect()->route('admin.users.index')
            ->with('message', 'تم إنشاء المستخدم بنجاح');
    }

    public function edit(User $user)
    {
        // التأكد من أن المستخدم ينتمي لنفس الـ tenant
        if ($user->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        $roles = Role::all()->pluck('name');
        $userRoles = $user->roles->pluck('name');

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $userRoles,
            ],
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user)
    {
        // التأكد من أن المستخدم ينتمي لنفس الـ tenant
        if ($user->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
            'password' => 'nullable|confirmed|min:8',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
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
        // التأكد من أن المستخدم ينتمي لنفس الـ tenant
        if ($user->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        // منع حذف المستخدم الحالي
        if ($user->id === Auth::id()) {
            return back()->with('error', 'لا يمكنك حذف حسابك الحالي');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('message', 'تم حذف المستخدم بنجاح');
    }

    public function resetPassword(Request $request, User $user)
    {
        // التأكد من أن المستخدم ينتمي لنفس الـ tenant
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