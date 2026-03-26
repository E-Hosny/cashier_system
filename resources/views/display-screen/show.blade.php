<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض الشاشة</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            background: #111;
        }
        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.8s ease-in-out;
        }
        .slide.active {
            opacity: 1;
            z-index: 1;
        }
        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .no-slides {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-family: system-ui, sans-serif;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    @if (isset($error) && $error === 'tenant_not_found')
        <div class="no-slides">الفرع غير موجود. تأكد من الرابط.</div>
    @elseif (empty($slides))
        <div class="no-slides">لا توجد صور للعرض</div>
    @else
        @foreach ($slides as $index => $slide)
            <div class="slide {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}" data-duration="{{ $slide['duration_seconds'] }}">
                <img src="{{ $slide['url'] }}" alt="شريحة {{ $index + 1 }}">
            </div>
        @endforeach
    @endif

    @if (!empty($slides))
    <script>
        (function() {
            var slideEls = document.querySelectorAll('.slide');
            var total = slideEls.length;
            var current = 0;
            var timeoutId;

            if (total <= 1) return;

            function show(index) {
                slideEls.forEach(function(s, i) {
                    s.classList.toggle('active', i === index);
                });
                current = index;
            }

            function scheduleNext() {
                var durationSec = parseInt(slideEls[current].getAttribute('data-duration') || '3', 10);
                var durationMs = Math.max(1000, durationSec * 1000);
                timeoutId = setTimeout(function() {
                    show((current + 1) % total);
                    scheduleNext();
                }, durationMs);
            }

            scheduleNext();
        })();
    </script>
    @endif
</body>
</html>
