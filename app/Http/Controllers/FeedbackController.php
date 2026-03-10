<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class FeedbackController extends Controller
{
    public function index()
    {
        $feedback = Feedback::approved()
            ->orderByLatest()
            ->paginate(10);

        $stats = [
            'total' => Feedback::approved()->count(),
            'average_rating' => round(Feedback::approved()->avg('rating'), 1),
            'rating_distribution' => [
                5 => Feedback::approved()->where('rating', 5)->count(),
                4 => Feedback::approved()->where('rating', 4)->count(),
                3 => Feedback::approved()->where('rating', 3)->count(),
                2 => Feedback::approved()->where('rating', 2)->count(),
                1 => Feedback::approved()->where('rating', 1)->count(),
            ]
        ];

        return Inertia::render('Feedback/Index', [
            'feedback' => $feedback,
            'stats' => $stats
        ]);
    }

    public function create()
    {
        return Inertia::render('Feedback/Create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $feedback = Feedback::create([
            'rating' => $request->rating,
            'comment' => $request->comment,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('feedback.index')
            ->with('success', 'تم إرسال تقييمك بنجاح! شكراً لك.');
    }

    /**
     * نموذج التقييم العام (بدون تسجيل). المحتوى والرابط حسب الـ tenant في الرابط.
     */
    public function publicForm(?string $tenant = null)
    {
        $tenantModel = $this->resolveTenant($tenant);
        $tenantParam = $tenantModel ? ($tenantModel->slug ?: (string) $tenantModel->id) : null;
        $formUrl = $tenantParam ? route('feedback.public.form', ['tenant' => $tenantParam]) : route('feedback.public.form');
        $displayUrl = $tenantParam ? route('feedback.public.display', ['tenant' => $tenantParam]) : route('feedback.public.display');

        return view('feedback.public-form', [
            'tenant_param' => $tenantParam,
            'form_url' => $formUrl,
            'display_url' => $displayUrl,
        ]);
    }

    public function publicStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
            'tenant' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenantModel = $this->resolveTenant($request->input('tenant'));
        $tenantId = $tenantModel?->id;

        $feedback = Feedback::withoutGlobalScope('tenant')->create([
            'rating' => $request->rating,
            'comment' => $request->comment,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'tenant_id' => $tenantId,
        ]);

        $backUrl = $tenantModel
            ? route('feedback.public.form', ['tenant' => $tenantModel->slug ?: $tenantModel->id])
            : route('feedback.public.form');

        return redirect()->to($backUrl)->with('success', 'تم إرسال تقييمك بنجاح! شكراً لك.');
    }

    /**
     * عرض التقييمات للجميع (بدون تسجيل). المحتوى حسب الـ tenant في الرابط.
     */
    public function publicDisplay(?string $tenant = null)
    {
        $tenantModel = $this->resolveTenant($tenant);
        $tenantId = $tenantModel?->id;

        $baseQuery = Feedback::withoutGlobalScope('tenant')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'));

        $feedback = (clone $baseQuery)->approved()->orderByLatest()->take(10)->get();

        $stats = [
            'total' => (clone $baseQuery)->approved()->count(),
            'average_rating' => round((clone $baseQuery)->approved()->avg('rating'), 1) ?: 0,
            'rating_distribution' => [
                5 => (clone $baseQuery)->approved()->where('rating', 5)->count(),
                4 => (clone $baseQuery)->approved()->where('rating', 4)->count(),
                3 => (clone $baseQuery)->approved()->where('rating', 3)->count(),
                2 => (clone $baseQuery)->approved()->where('rating', 2)->count(),
                1 => (clone $baseQuery)->approved()->where('rating', 1)->count(),
            ]
        ];

        $tenantParam = $tenantModel ? ($tenantModel->slug ?: (string) $tenantModel->id) : null;
        $formUrl = $tenantParam ? route('feedback.public.form', ['tenant' => $tenantParam]) : route('feedback.public.form');

        return view('feedback.public-display', compact('feedback', 'stats', 'tenantParam', 'formUrl'));
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
} 