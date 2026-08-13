<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JobApplicationController extends Controller
{
    /**
     * نموذج التقديم على الوظيفة (عام — بدون تسجيل).
     */
    public function publicForm(?string $tenant = null)
    {
        $tenantModel = $this->resolveTenant($tenant);
        $tenantParam = $tenantModel ? ($tenantModel->slug ?: (string) $tenantModel->id) : null;

        return view('job-applications.public-form', [
            'tenant_param' => $tenantParam,
            'tenant_name' => $this->tenantDisplayName($tenantModel),
            'tenant_logo_url' => $tenantModel?->logo_url,
            'form_url' => $tenantParam
                ? route('job-applications.public.form', ['tenant' => $tenantParam])
                : route('job-applications.public.form'),
        ]);
    }

    public function publicStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:30',
            'age' => 'required|integer|min:16|max:70',
            'tenant' => 'nullable|string',
        ], [
            'name.required' => 'الاسم مطلوب.',
            'address.required' => 'العنوان مطلوب.',
            'phone.required' => 'رقم التليفون مطلوب.',
            'age.required' => 'السن مطلوب.',
            'age.integer' => 'السن يجب أن يكون رقماً صحيحاً.',
            'age.min' => 'السن يجب أن يكون 16 سنة على الأقل.',
            'age.max' => 'السن يجب ألا يتجاوز 70 سنة.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenantModel = $this->resolveTenant($request->input('tenant'));
        $tenantId = $tenantModel?->id;

        JobApplication::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'age' => $request->age,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $backUrl = $tenantModel
            ? route('job-applications.public.form', ['tenant' => $tenantModel->slug ?: $tenantModel->id])
            : route('job-applications.public.form');

        return redirect()->to($backUrl)->with('success', 'سيتم التواصل معك');
    }

    private function resolveTenant(?string $tenant): ?Tenant
    {
        if ($tenant === null || $tenant === '') {
            return Tenant::orderBy('id')->first();
        }

        return is_numeric($tenant)
            ? Tenant::find($tenant)
            : Tenant::where('slug', $tenant)->first();
    }

    /**
     * اسم العرض للمستأجر — نخفي الاسم الافتراضي للنظام (slug: default).
     */
    private function tenantDisplayName(?Tenant $tenant): ?string
    {
        if (! $tenant || $tenant->slug === 'default') {
            return null;
        }

        return $tenant->name;
    }
}
