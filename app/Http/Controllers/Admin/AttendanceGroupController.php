<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceGroup;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AttendanceGroupController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Employees/AttendanceGroups/Index', [
            'groups' => AttendanceGroup::withCount('employees')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'max_present' => 'required|integer|min:1|max:20',
        ]);

        AttendanceGroup::create($validated);

        return redirect()->route('admin.employees.attendance-groups.index')
            ->with('success', 'تم إنشاء مجموعة الحضور بنجاح');
    }

    public function update(Request $request, AttendanceGroup $attendanceGroup)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'max_present' => 'required|integer|min:1|max:20',
        ]);

        $attendanceGroup->update($validated);

        return redirect()->route('admin.employees.attendance-groups.index')
            ->with('success', 'تم تحديث مجموعة الحضور بنجاح');
    }

    public function destroy(AttendanceGroup $attendanceGroup)
    {
        $attendanceGroup->delete();

        return redirect()->route('admin.employees.attendance-groups.index')
            ->with('success', 'تم حذف مجموعة الحضور بنجاح');
    }
}

