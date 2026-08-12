<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقديم على وظيفة{{ $tenant_name ? ' — ' . $tenant_name : '' }}</title>
    <meta name="description" content="قدّم طلبك للانضمام إلى فريقنا">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 45%, #f8fafc 100%);
            min-height: 100vh;
            direction: rtl;
            text-align: right;
            color: #1e293b;
        }

        .bg-shapes {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }

        .bg-shapes span {
            position: absolute;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.08);
            animation: float 8s ease-in-out infinite;
        }

        .bg-shapes span:nth-child(1) { width: 280px; height: 280px; top: -80px; left: -60px; }
        .bg-shapes span:nth-child(2) { width: 180px; height: 180px; bottom: 10%; right: 8%; animation-delay: 2s; }
        .bg-shapes span:nth-child(3) { width: 120px; height: 120px; top: 35%; left: 12%; animation-delay: 4s; }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-18px); }
        }

        .page {
            position: relative;
            z-index: 1;
            max-width: 640px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo-wrap {
            width: 110px;
            height: 110px;
            margin: 0 auto 16px;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 16px 40px rgba(79, 70, 229, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px;
            overflow: hidden;
        }

        .logo-wrap img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .logo-placeholder {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
        }

        .brand h1 {
            font-size: 1.85rem;
            font-weight: 700;
            color: #312e81;
            margin-bottom: 8px;
        }

        .brand p {
            color: #64748b;
            font-size: 1.05rem;
            line-height: 1.6;
        }

        .card {
            background: #fff;
            border-radius: 24px;
            padding: 32px 28px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.08);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #818cf8, #a5b4fc);
        }

        .success-box {
            display: none;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal-overlay.is-visible {
            opacity: 1;
            visibility: visible;
        }

        .success-modal {
            background: #fff;
            border-radius: 28px;
            max-width: 420px;
            width: 100%;
            padding: 40px 32px 32px;
            text-align: center;
            box-shadow: 0 32px 64px rgba(15, 23, 42, 0.2);
            transform: scale(0.85) translateY(20px);
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .modal-overlay.is-visible .success-modal {
            transform: scale(1) translateY(0);
        }

        .success-modal::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 5px;
            background: linear-gradient(90deg, #6366f1, #22c55e);
        }

        .success-icon-wrap {
            width: 88px;
            height: 88px;
            margin: 0 auto 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: iconPop 0.5s ease 0.2s both;
        }

        .success-icon-wrap i {
            font-size: 2.5rem;
            color: #059669;
        }

        @keyframes iconPop {
            0% { transform: scale(0); opacity: 0; }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .success-modal h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
        }

        .success-modal p {
            color: #64748b;
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 28px;
        }

        .success-modal p strong {
            color: #4f46e5;
            font-weight: 600;
        }

        .modal-close-btn {
            width: 100%;
            padding: 14px 20px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #fff;
            font-family: 'Cairo', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
        }

        .modal-close-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(79, 70, 229, 0.3);
        }

        .confetti-dot {
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            opacity: 0;
            animation: confetti 1.2s ease forwards;
        }

        @keyframes confetti {
            0% { opacity: 1; transform: translate(0, 0) scale(1); }
            100% { opacity: 0; transform: translate(var(--tx), var(--ty)) scale(0); }
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .form-group { margin-bottom: 22px; }

        .form-label {
            display: block;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .form-label i {
            color: #6366f1;
            margin-left: 6px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-family: 'Cairo', sans-serif;
            font-size: 1rem;
            background: #f8fafc;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }

        .form-input.is-invalid {
            border-color: #f87171;
            background: #fff5f5;
        }

        .field-error {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 6px;
        }

        .submit-btn {
            width: 100%;
            margin-top: 8px;
            padding: 15px 20px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #fff;
            font-family: 'Cairo', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 12px 24px rgba(79, 70, 229, 0.25);
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(79, 70, 229, 0.3);
        }

        .submit-btn:active { transform: translateY(0); }

        .footer-note {
            text-align: center;
            margin-top: 24px;
            color: #94a3b8;
            font-size: 0.9rem;
        }

        @media (max-width: 480px) {
            .page { padding: 20px 16px 36px; }
            .card { padding: 24px 18px; border-radius: 20px; }
            .brand h1 { font-size: 1.55rem; }
        }
    </style>
</head>
<body>
    <div class="bg-shapes" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <main class="page">
        <header class="brand">
            <div class="logo-wrap">
                @if(!empty($tenant_logo_url))
                    <img src="{{ $tenant_logo_url }}" alt="{{ $tenant_name ?? 'الشعار' }}">
                @else
                    <div class="logo-placeholder" aria-hidden="true">
                        <i class="fas fa-briefcase"></i>
                    </div>
                @endif
            </div>
            <h1>التقديم على وظيفة</h1>
            @if(!empty($tenant_name))
                <p>انضم إلى فريق <strong>{{ $tenant_name }}</strong> — املأ البيانات التالية وسنتواصل معك قريباً.</p>
            @else
                <p>املأ البيانات التالية وسنتواصل معك قريباً.</p>
            @endif
        </header>

        <section class="card">
            @if($errors->any())
                <div class="error-box">
                    <ul style="margin: 0; padding-right: 18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('job-applications.public.store') }}" novalidate>
                @csrf
                @if(!empty($tenant_param))
                    <input type="hidden" name="tenant" value="{{ $tenant_param }}">
                @endif

                <div class="form-group">
                    <label class="form-label" for="name">
                        <i class="fas fa-user"></i>
                        الاسم
                    </label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        class="form-input @error('name') is-invalid @enderror"
                        value="{{ old('name') }}"
                        required
                        autocomplete="name"
                    >
                    @error('name')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">
                        <i class="fas fa-location-dot"></i>
                        العنوان
                    </label>
                    <input
                        id="address"
                        type="text"
                        name="address"
                        class="form-input @error('address') is-invalid @enderror"
                        value="{{ old('address') }}"
                        required
                        autocomplete="street-address"
                    >
                    @error('address')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="age">
                        <i class="fas fa-calendar"></i>
                        السن
                    </label>
                    <input
                        id="age"
                        type="number"
                        name="age"
                        min="16"
                        max="70"
                        class="form-input @error('age') is-invalid @enderror"
                        value="{{ old('age') }}"
                        required
                        inputmode="numeric"
                    >
                    @error('age')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">
                        <i class="fas fa-phone"></i>
                        رقم التليفون
                    </label>
                    <input
                        id="phone"
                        type="tel"
                        name="phone"
                        class="form-input @error('phone') is-invalid @enderror"
                        value="{{ old('phone') }}"
                        required
                        dir="ltr"
                        style="text-align: left;"
                        autocomplete="tel"
                    >
                    @error('phone')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane" style="margin-left: 8px;"></i>
                    إرسال الطلب
                </button>
            </form>
        </section>

        <p class="footer-note">بياناتك تُستخدم للتواصل معك بخصوص فرص العمل فقط.</p>
    </main>

    @if(session('success'))
    <div id="successModal" class="modal-overlay is-visible" role="dialog" aria-modal="true" aria-labelledby="successModalTitle">
        <div class="success-modal">
            <div class="success-icon-wrap">
                <i class="fas fa-circle-check"></i>
            </div>
            <h2 id="successModalTitle">شكراً لتقديمك!</h2>
            <p>
                تم استلام طلبك بنجاح.<br>
                <strong>سيتم التواصل معك</strong> في أقرب وقت ممكن.
            </p>
            <button type="button" class="modal-close-btn" id="closeSuccessModal">
                حسناً
            </button>
        </div>
    </div>
    @endif

    <script>
        (function () {
            var modal = document.getElementById('successModal');
            if (!modal) return;

            var closeBtn = document.getElementById('closeSuccessModal');

            function closeModal() {
                modal.classList.remove('is-visible');
                document.body.style.overflow = '';
                setTimeout(function () {
                    modal.remove();
                }, 300);
            }

            closeBtn.addEventListener('click', closeModal);

            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeModal();
            });

            document.body.style.overflow = 'hidden';
        })();
    </script>
</body>
</html>
