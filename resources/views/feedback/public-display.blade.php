<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقييمات العملاء - محل العصائر</title>
    <meta name="description" content="شاهد تقييمات العملاء لمحل العصائر">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
            direction: rtl;
            text-align: right;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            border-radius: 50%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            background: white;
            padding: 15px;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #166534;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .subtitle {
            font-size: 1.2rem;
            color: #6b7280;
            margin-bottom: 30px;
        }
        
        .stats-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            direction: rtl;
        }
        
        .stats-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #22c55e;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .rating-distribution {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            direction: rtl;
        }
        
        .distribution-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 20px;
            text-align: center;
            direction: rtl;
        }
        
        .distribution-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stars {
            width: 80px;
            color: #eab308;
            font-size: 0.9rem;
        }
        
        .progress-bar {
            flex: 1;
            height: 20px;
            background: #f3f4f6;
            border-radius: 10px;
            margin: 0 15px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #eab308, #f59e0b);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        .count {
            width: 50px;
            text-align: left;
            font-weight: 600;
            color: #374151;
        }
        
        .feedback-list {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            direction: rtl;
        }
        
        .feedback-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 20px;
            text-align: center;
            direction: rtl;
        }
        
        .feedback-item {
            border-bottom: 1px solid #f3f4f6;
            padding: 20px 0;
        }
        
        .feedback-item:last-child {
            border-bottom: none;
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .customer-name {
            font-weight: 600;
            color: #374151;
            font-size: 1.1rem;
        }
        
        .feedback-stars {
            color: #eab308;
            font-size: 1.1rem;
        }
        
        .feedback-date {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .feedback-comment {
            color: #4b5563;
            line-height: 1.6;
            margin-top: 10px;
        }
        
        .cta-section {
            text-align: center;
            margin-top: 40px;
            direction: rtl;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(34, 197, 94, 0.4);
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            color: #6b7280;
            font-size: 0.9rem;
            direction: rtl;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .feedback-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .distribution-item {
                flex-direction: row-reverse;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="/images/mylogo.png" alt="محل العصائر">
            </div>
            <h1 class="title">تقييمات العملاء</h1>
            <p class="subtitle">شاهد ما يقوله عملاؤنا عن خدماتنا</p>
        </div>
        
        <!-- إحصائيات سريعة -->
        <div class="stats-section">
            <h3 class="stats-title">إحصائيات التقييمات</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">{{ $stats['average_rating'] ?? 0 }}</div>
                    <div class="stat-label">متوسط التقييم</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">{{ $stats['rating_distribution'][5] ?? 0 }}</div>
                    <div class="stat-label">5 نجوم</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">{{ $stats['rating_distribution'][4] ?? 0 }}</div>
                    <div class="stat-label">4 نجوم</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">{{ $stats['rating_distribution'][3] ?? 0 }}</div>
                    <div class="stat-label">3 نجوم</div>
                </div>
            </div>
        </div>
        
        <!-- توزيع التقييمات -->
        <div class="rating-distribution">
            <h3 class="distribution-title">توزيع التقييمات</h3>
            <div class="distribution-item">
                <div class="stars">⭐⭐⭐⭐⭐</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $stats['total'] > 0 ? ($stats['rating_distribution'][5] ?? 0) / $stats['total'] * 100 : 0 }}%"></div>
                </div>
                <div class="count">{{ $stats['rating_distribution'][5] ?? 0 }}</div>
            </div>
            <div class="distribution-item">
                <div class="stars">⭐⭐⭐⭐</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $stats['total'] > 0 ? ($stats['rating_distribution'][4] ?? 0) / $stats['total'] * 100 : 0 }}%"></div>
                </div>
                <div class="count">{{ $stats['rating_distribution'][4] ?? 0 }}</div>
            </div>
            <div class="distribution-item">
                <div class="stars">⭐⭐⭐</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $stats['total'] > 0 ? ($stats['rating_distribution'][3] ?? 0) / $stats['total'] * 100 : 0 }}%"></div>
                </div>
                <div class="count">{{ $stats['rating_distribution'][3] ?? 0 }}</div>
            </div>
            <div class="distribution-item">
                <div class="stars">⭐⭐</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $stats['total'] > 0 ? ($stats['rating_distribution'][2] ?? 0) / $stats['total'] * 100 : 0 }}%"></div>
                </div>
                <div class="count">{{ $stats['rating_distribution'][2] ?? 0 }}</div>
            </div>
            <div class="distribution-item">
                <div class="stars">⭐</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $stats['total'] > 0 ? ($stats['rating_distribution'][1] ?? 0) / $stats['total'] * 100 : 0 }}%"></div>
                </div>
                <div class="count">{{ $stats['rating_distribution'][1] ?? 0 }}</div>
            </div>
        </div>
        
        <!-- قائمة التقييمات -->
        <div class="feedback-list">
            <h3 class="feedback-title">آخر التقييمات</h3>
            
            @forelse($feedback as $item)
                <div class="feedback-item">
                    <div class="feedback-header">
                        <div class="feedback-stars">
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= $item->rating)
                                    ⭐
                                @else
                                    ☆
                                @endif
                            @endfor
                        </div>
                        <div class="feedback-date">{{ $item->created_at->format('Y-m-d') }}</div>
                    </div>
                    
                    @if($item->comment)
                        <div class="feedback-comment">{{ $item->comment }}</div>
                    @endif
                </div>
            @empty
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-star text-4xl text-gray-300 mb-4"></i>
                    <p>لا توجد تقييمات بعد</p>
                </div>
            @endforelse
        </div>
        
        <!-- دعوة للعمل -->
        <div class="cta-section">
            <a href="/feedback" class="cta-button">
                <i class="fas fa-star ml-2"></i>
                أضف تقييمك الآن
            </a>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} محل العصائر - جميع الحقوق محفوظة</p>
        </div>
    </div>
</body>
</html> 