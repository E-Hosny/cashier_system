<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobApplicationController extends Controller
{
    public function index(Request $request)
    {
        $applications = JobApplication::query()
            ->with(['noteAuthor:id,name'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $publicFormUrl = '';
        $user = $request->user();
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant) {
                $param = $tenant->slug ?: (string) $tenant->id;
                $publicFormUrl = url()->route('job-applications.public.form', ['tenant' => $param]);
            }
        }
        if ($publicFormUrl === '') {
            $publicFormUrl = url()->route('job-applications.public.form');
        }

        return Inertia::render('Admin/JobApplications/Index', [
            'applications' => $applications,
            'totalApplications' => JobApplication::count(),
            'publicFormUrl' => $publicFormUrl,
        ]);
    }

    public function updateNote(Request $request, JobApplication $jobApplication)
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
        ], [
            'note.max' => 'الملحوظة يجب ألا تتجاوز 2000 حرف.',
        ]);

        $note = isset($validated['note']) ? trim($validated['note']) : '';
        $note = $note === '' ? null : $note;

        $jobApplication->update([
            'note' => $note,
            'note_by_user_id' => $note ? $request->user()->id : null,
            'note_updated_at' => $note ? now() : null,
        ]);

        return back()->with('success', 'تم حفظ الملحوظة');
    }

    public function destroy(JobApplication $jobApplication)
    {
        $jobApplication->delete();

        return back()->with('success', 'تم حذف الطلب بنجاح');
    }
}
