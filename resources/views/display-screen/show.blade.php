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
    @if (empty($imageUrls))
        <div class="no-slides">لا توجد صور للعرض</div>
    @else
        @foreach ($imageUrls as $index => $url)
            <div class="slide {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}">
                <img src="{{ $url }}" alt="شريحة {{ $index + 1 }}">
            </div>
        @endforeach
    @endif

    @if (!empty($imageUrls))
    <script>
        (function() {
            var slides = document.querySelectorAll('.slide');
            var total = slides.length;
            var current = 0;
            var intervalMs = {{ (int) $intervalSeconds }} * 1000;

            if (total <= 1) return;

            function show(index) {
                slides.forEach(function(s, i) {
                    s.classList.toggle('active', i === index);
                });
                current = index;
            }

            function next() {
                show((current + 1) % total);
            }

            setInterval(next, intervalMs);
        })();
    </script>
    @endif
</body>
</html>
