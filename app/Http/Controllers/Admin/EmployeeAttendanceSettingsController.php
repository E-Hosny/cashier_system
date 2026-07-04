<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDeductionRule;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeAttendanceSettingsController extends Controller
{
    private function requireManagerRoles(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasAnyRole(['admin', 'super admin'])) {
            abort(403);
        }
    }

    public function index(): Response
    {
        $this->requireManagerRoles();

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'expected_checkin_time', 'expected_checkout_time', 'grace_minutes', 'late_deductions_enabled'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'position' => $e->position,
                'expected_checkin_time' => $e->formattedExpectedCheckinTime(),
                'expected_checkout_time' => $e->formattedExpectedCheckoutTime(),
                'grace_minutes' => (int) $e->grace_minutes,
                'late_deductions_enabled' => (bool) $e->late_deductions_enabled,
            ]);

        $rules = AttendanceDeductionRule::query()
            ->orderBy('sort_order')
            ->orderBy('min_late_minutes')
            ->get()
            ->map(fn (AttendanceDeductionRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'rule_type' => $rule->rule_type ?? 'range',
                'rule_type_label' => $rule->ruleTypeLabel(),
                'min_late_minutes' => $rule->min_late_minutes,
                'max_late_minutes' => $rule->max_late_minutes,
                'deduction_amount' => (float) $rule->deduction_amount,
                'is_active' => $rule->is_active,
                'sort_order' => $rule->sort_order,
                'range_label' => $rule->rangeLabel(),
            ]);

        return Inertia::render('Admin/Employees/AttendanceSettings/Index', [
            'employees' => $employees,
            'rules' => $rules,
        ]);
    }

    public function updateSchedules(Request $request): RedirectResponse
    {
        $this->requireManagerRoles();

        $data = $request->validate([
            'schedules' => 'required|array|min:1',
            'schedules.*.id' => 'required|exists:employees,id',
            'schedules.*.expected_checkin_time' => 'nullable|date_format:H:i',
            'schedules.*.expected_checkout_time' => 'nullable|date_format:H:i',
            'schedules.*.grace_minutes' => 'nullable|integer|min:0|max:120',
            'schedules.*.late_deductions_enabled' => 'boolean',
        ]);

        foreach ($data['schedules'] as $row) {
            $employee = Employee::findOrFail($row['id']);
            $employee->update([
                'expected_checkin_time' => $row['expected_checkin_time'] ?: null,
                'expected_checkout_time' => $row['expected_checkout_time'] ?: null,
                'grace_minutes' => $row['grace_minutes'] ?? 0,
                'late_deductions_enabled' => $row['late_deductions_enabled'] ?? true,
            ]);
        }

        return back()->with('success', 'تم حفظ مواعيد الحضور والانصراف.');
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $this->requireManagerRoles();

        $data = $this->validateRule($request);

        AttendanceDeductionRule::create([
            'name' => $data['name'],
            'rule_type' => $data['rule_type'],
            'min_late_minutes' => $data['min_late_minutes'],
            'max_late_minutes' => $data['max_late_minutes'],
            'deduction_amount' => $data['deduction_amount'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'تم إضافة قانون الخصم.');
    }

    public function updateRule(Request $request, AttendanceDeductionRule $attendanceDeductionRule): RedirectResponse
    {
        $this->requireManagerRoles();

        $data = $this->validateRule($request);

        $attendanceDeductionRule->update([
            'name' => $data['name'],
            'rule_type' => $data['rule_type'],
            'min_late_minutes' => $data['min_late_minutes'],
            'max_late_minutes' => $data['max_late_minutes'],
            'deduction_amount' => $data['deduction_amount'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'تم تحديث قانون الخصم.');
    }

    public function destroyRule(AttendanceDeductionRule $attendanceDeductionRule): RedirectResponse
    {
        $this->requireManagerRoles();

        $attendanceDeductionRule->delete();

        return back()->with('success', 'تم حذف قانون الخصم.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRule(Request $request): array
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'rule_type' => 'required|in:range,more_than',
            'min_late_minutes' => 'required|integer|min:1|max:600',
            'max_late_minutes' => 'nullable|integer|min:1|max:600',
            'deduction_amount' => 'required|numeric|min:0.01',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        if ($data['rule_type'] === 'range') {
            $request->validate([
                'max_late_minutes' => 'required|integer|min:1|max:600|gte:min_late_minutes',
            ]);
        } else {
            $data['max_late_minutes'] = null;
        }

        $data['name'] = $data['name'] ?: null;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
