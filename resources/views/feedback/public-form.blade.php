<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقييم محل العصائر</title>
    <meta name="description" content="شاركنا رأيك في محل العصائر - تقييمك مهم لنا">
    
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
        
        .feedback-form {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            text-align: right;
        }
        
        .feedback-form::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 5px;
            background: linear-gradient(90deg, #eab308, #22c55e);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            font-size: 1.1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Cairo', sans-serif;
            transition: all 0.3s ease;
            background: #f9fafb;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #22c55e;
            background: white;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        .rating-group {
            text-align: center;
            margin: 20px 0;
        }
        
        .rating-stars {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 15px 0;
            flex-direction: row-reverse;
        }
        
        .star {
            font-size: 2.5rem;
            color: #d1d5db;
            cursor: pointer;
            transition: all 0.3s ease;
            transform: scale(1);
        }
        
        .star:hover {
            transform: scale(1.1);
        }
        
        .star.active {
            color: #eab308;
            transform: scale(1.1);
        }
        
        .star.filled {
            color: #eab308;
        }
        
        .rating-text {
            font-size: 1.1rem;
            color: #6b7280;
            margin-top: 10px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 600;
            font-family: 'Cairo', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
            text-align: center;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(34, 197, 94, 0.4);
        }
        
                .submit-btn:active {
            transform: translateY(0);
        }
        

        
        .success-message {
            background: #dcfce7;
            border: 1px solid #22c55e;
            color: #166534;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        .error-message {
            background: #fef2f2;
            border: 1px solid #ef4444;
            color: #dc2626;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        

        
        .cta-section {
            text-align: center;
            margin-top: 30px;
            direction: rtl;
        }
        
        .cta-link {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }
        
        .cta-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
            background: linear-gradient(135deg, #16a34a, #15803d);
        }
        
        .cta-link i {
            margin-left: 8px;
            font-size: 1.1rem;
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
            
            .feedback-form {
                padding: 25px;
                margin: 0 15px;
            }
            
            .star {
                font-size: 2rem;
            }
            
            .rating-stars {
                flex-direction: row-reverse;
            }
        }
        
        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            background: linear-gradient(45deg, rgba(34, 197, 94, 0.1), rgba(234, 179, 8, 0.1));
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            right: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 20%;
            left: 15%;
            animation-delay: 2s;
        }
        
        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            right: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="/images/mylogo.png" alt="محل العصائر">
            </div>
            <h1 class="title">قيم تجربتك معنا</h1>
            <p class="subtitle">شاركنا رأيك وساعدنا في تحسين خدماتنا</p>
        </div>
        
                    <form class="feedback-form" method="POST" action="{{ route('feedback.public.store') }}">
                @csrf
                
                @if(session('success'))
                    <div class="success-message">
                        {{ session('success') }}
                    </div>
                @endif
                
                @if($errors->any())
                    <div class="error-message">
                        @foreach($errors->all() as $error)
                            {{ $error }}<br>
                        @endforeach
                    </div>
                @endif
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-star text-yellow-500 ml-2"></i>
                    التقييم
                </label>
                <div class="rating-group">
                    <div class="rating-stars" id="rating-stars">
                        <i class="star fas fa-star" data-rating="1"></i>
                        <i class="star fas fa-star" data-rating="2"></i>
                        <i class="star fas fa-star" data-rating="3"></i>
                        <i class="star fas fa-star" data-rating="4"></i>
                        <i class="star fas fa-star" data-rating="5"></i>
                    </div>
                    <div class="rating-text" id="rating-text">اختر عدد النجوم</div>
                    <input type="hidden" name="rating" id="rating-input" value="{{ old('rating') }}" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="comment">
                    <i class="fas fa-comment text-green-500 ml-2"></i>
                    تعليق (اختياري)
                </label>
                <textarea id="comment" 
                          name="comment" 
                          class="form-input @error('comment') border-red-500 @enderror" 
                          rows="4" 
                          placeholder="اكتب تعليقك هنا...">{{ old('comment') }}</textarea>
            </div>
            
            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane ml-2"></i>
                إرسال التقييم
            </button>
                </form>
        
        <!-- رابط عرض جميع التقييمات -->
        <div class="cta-section">
            <a href="{{ route('feedback.public.display') }}" class="cta-link">
                <i class="fas fa-eye"></i>
                عرض جميع التقييمات
            </a>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} محل العصائر - جميع الحقوق محفوظة</p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star');
            const ratingInput = document.getElementById('rating-input');
            const ratingText = document.getElementById('rating-text');
            
            const ratingTexts = {
                1: 'سيء جداً',
                2: 'سيء',
                3: 'مقبول',
                4: 'جيد',
                5: 'ممتاز'
            };
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = this.dataset.rating;
                    ratingInput.value = rating;
                    
                    // Update stars display
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.add('filled');
                            s.classList.remove('active');
                        } else {
                            s.classList.remove('filled', 'active');
                        }
                    });
                    
                    // Update rating text
                    ratingText.textContent = ratingTexts[rating];
                });
                
                star.addEventListener('mouseenter', function() {
                    const rating = this.dataset.rating;
                    
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                    
                    ratingText.textContent = ratingTexts[rating];
                });
                
                star.addEventListener('mouseleave', function() {
                    stars.forEach(s => s.classList.remove('active'));
                    
                    if (ratingInput.value) {
                        ratingText.textContent = ratingTexts[ratingInput.value];
                    } else {
                        ratingText.textContent = 'اختر عدد النجوم';
                    }
                });
            });
            
            // Set initial rating if exists
            if (ratingInput.value) {
                const rating = parseInt(ratingInput.value);
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('filled');
                    }
                });
                ratingText.textContent = ratingTexts[rating];
            }
        });
    </script>
</body>
</html> 