<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Feedback;

class FeedbackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $feedbacks = [
            [
                'rating' => 5,
                'comment' => 'عصائر ممتازة وطازجة! الخدمة سريعة والموظفين ودودين جداً. أنصح الجميع بزيارة المحل.',
                'is_approved' => true,
                'created_at' => now()->subDays(2),
            ],
            [
                'rating' => 5,
                'comment' => 'أفضل محل عصائر في المنطقة! النكهات طبيعية 100% والأسعار معقولة.',
                'is_approved' => true,
                'created_at' => now()->subDays(3),
            ],
            [
                'rating' => 4,
                'comment' => 'عصائر لذيذة ومتنوعة. المكان نظيف والخدمة جيدة.',
                'is_approved' => true,
                'created_at' => now()->subDays(4),
            ],
            [
                'rating' => 5,
                'comment' => 'أحب عصير البرتقال الطازج! المحل دائم النظافة والموظفين محترمين.',
                'is_approved' => true,
                'created_at' => now()->subDays(5),
            ],
            [
                'rating' => 4,
                'comment' => 'جودة عالية وأسعار مناسبة. أنصح بالزيارة.',
                'is_approved' => true,
                'created_at' => now()->subDays(6),
            ],
            [
                'rating' => 5,
                'comment' => 'أفضل عصير مانجو جربته! المحل منظم جداً والخدمة ممتازة.',
                'is_approved' => true,
                'created_at' => now()->subDays(7),
            ],
            [
                'rating' => 3,
                'comment' => 'العصائر جيدة لكن يمكن تحسين سرعة الخدمة قليلاً.',
                'is_approved' => true,
                'created_at' => now()->subDays(8),
            ],
            [
                'rating' => 5,
                'comment' => 'محل ممتاز! عصائر طازجة وطبيعية. المكان نظيف والموظفين متعاونين.',
                'is_approved' => true,
                'created_at' => now()->subDays(9),
            ],
        ];

        foreach ($feedbacks as $feedback) {
            Feedback::create($feedback);
        }
    }
} 