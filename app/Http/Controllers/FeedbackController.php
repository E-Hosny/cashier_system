<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
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

    public function publicForm()
    {
        return view('feedback.public-form');
    }

    public function publicStore(Request $request)
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

        return back()->with('success', 'تم إرسال تقييمك بنجاح! شكراً لك.');
    }

    public function publicDisplay()
    {
        $feedback = Feedback::approved()
            ->orderByLatest()
            ->take(10)
            ->get();

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

        return view('feedback.public-display', compact('feedback', 'stats'));
    }
} 