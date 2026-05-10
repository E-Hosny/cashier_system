<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'is_active' => $b->is_active,
                'created_at' => $b->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/Branches/Index', [
            'branches' => $branches,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        Branch::create([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('admin.branches.index')->with('success', 'تم إنشاء الفرع.');
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorizeBranch($branch);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $branch->update($validated);

        return redirect()->route('admin.branches.index')->with('success', 'تم تحديث الفرع.');
    }

    public function destroy(Branch $branch)
    {
        $this->authorizeBranch($branch);

        if ($branch->orders()->exists()) {
            return back()->with('error', 'لا يمكن حذف فرع يحتوي على فواتير.');
        }

        $branch->delete();

        return redirect()->route('admin.branches.index')->with('success', 'تم حذف الفرع.');
    }

    private function authorizeBranch(Branch $branch): void
    {
        abort_unless((int) $branch->tenant_id === (int) Auth::user()->tenant_id, 403);
    }
}
