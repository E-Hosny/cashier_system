<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeedbackController extends Controller
{
    public function index()
    {
        $feedback = Feedback::orderBy('created_at', 'desc')
            ->paginate(15);

        $stats = [
            'total' => Feedback::count(),
            'approved' => Feedback::where('is_approved', true)->count(),
            'pending' => Feedback::where('is_approved', false)->count(),
            'average_rating' => round(Feedback::where('is_approved', true)->avg('rating'), 1),
            'rating_distribution' => [
                5 => Feedback::where('is_approved', true)->where('rating', 5)->count(),
                4 => Feedback::where('is_approved', true)->where('rating', 4)->count(),
                3 => Feedback::where('is_approved', true)->where('rating', 3)->count(),
                2 => Feedback::where('is_approved', true)->where('rating', 2)->count(),
                1 => Feedback::where('is_approved', true)->where('rating', 1)->count(),
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
        $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ]);

        Feedback::create([
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_approved' => true, // Admin created feedback is auto-approved
        ]);

        return redirect()->route('admin.feedback.index')
            ->with('success', 'تم إضافة التقييم بنجاح');
    }

    public function approve($id)
    {
        $feedback = Feedback::findOrFail($id);
        $feedback->update(['is_approved' => true]);

        return back()->with('success', 'تم الموافقة على التقييم');
    }

    public function destroy($id)
    {
        $feedback = Feedback::findOrFail($id);
        $feedback->delete();

        return back()->with('success', 'تم حذف التقييم');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:approve,delete',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:feedback,id'
        ]);

        $ids = $request->ids;

        switch ($request->action) {
            case 'approve':
                Feedback::whereIn('id', $ids)->update(['is_approved' => true]);
                $message = 'تم الموافقة على التقييمات المحددة';
                break;
            
            case 'delete':
                Feedback::whereIn('id', $ids)->delete();
                $message = 'تم حذف التقييمات المحددة';
                break;
        }

        return back()->with('success', $message);
    }
} 