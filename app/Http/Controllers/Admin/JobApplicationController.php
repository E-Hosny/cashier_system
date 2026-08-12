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

    public function destroy(JobApplication $jobApplication)
    {
        $jobApplication->delete();

        return back()->with('success', 'تم حذف الطلب بنجاح');
    }
}
