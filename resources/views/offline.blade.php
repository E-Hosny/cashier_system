<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>النظام يعمل بدون إنترنت</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .offline-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .offline-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 15px;
            color: #fff;
        }
        .status {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .sync-status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
        }
        .btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            margin: 10px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        .connection-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .online {
            background: #4CAF50;
            color: white;
        }
        .offline {
            background: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <div class="connection-status" id="connectionStatus">
        <span id="statusText">جاري التحقق...</span>
    </div>

    <div class="offline-container">
        <div class="offline-icon">📱</div>
        <h1>النظام يعمل بدون إنترنت</h1>
        <p>يمكنك الاستمرار في العمل بشكل طبيعي. سيتم مزامنة البيانات عند عودة الإنترنت.</p>
        
        <div class="status">
            <strong>حالة الاتصال:</strong>
            <span id="connectionInfo">جاري التحقق...</span>
        </div>

        <div class="sync-status">
            <strong>الطلبات المحفوظة محلياً:</strong>
            <span id="offlineOrdersCount">0</span>
        </div>

        <div>
            <button class="btn" onclick="checkConnection()">فحص الاتصال</button>
            <button class="btn" onclick="syncOrders()">مزامنة الطلبات</button>
            <button class="btn" onclick="goToCashier()">العودة للكاشير</button>
        </div>
    </div>

    <script>
        let isOnline = navigator.onLine;
        let offlineOrdersCount = 0;

        // Update connection status
        function updateConnectionStatus() {
            const statusElement = document.getElementById('connectionStatus');
            const statusText = document.getElementById('statusText');
            const connectionInfo = document.getElementById('connectionInfo');

            if (isOnline) {
                statusElement.className = 'connection-status online';
                statusText.textContent = 'متصل بالإنترنت';
                connectionInfo.textContent = 'متصل بالإنترنت';
            } else {
                statusElement.className = 'connection-status offline';
                statusText.textContent = 'غير متصل';
                connectionInfo.textContent = 'غير متصل بالإنترنت';
            }
        }

        // Check connection
        function checkConnection() {
            fetch('/api/health-check', { method: 'HEAD' })
                .then(() => {
                    isOnline = true;
                    updateConnectionStatus();
                    if (offlineOrdersCount > 0) {
                        syncOrders();
                    }
                })
                .catch(() => {
                    isOnline = false;
                    updateConnectionStatus();
                });
        }

        // Sync offline orders
        async function syncOrders() {
            try {
                const response = await fetch('/api/sync-offline-orders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (response.ok) {
                    const result = await response.json();
                    offlineOrdersCount = result.remaining_orders;
                    document.getElementById('offlineOrdersCount').textContent = offlineOrdersCount;
                    alert(`تم مزامنة ${result.synced_orders} طلب بنجاح`);
                }
            } catch (error) {
                console.error('Sync failed:', error);
                alert('فشل في المزامنة. تأكد من الاتصال بالإنترنت.');
            }
        }

        // Go to cashier
        function goToCashier() {
            window.location.href = '/cashier';
        }

        // Event listeners
        window.addEventListener('online', () => {
            isOnline = true;
            updateConnectionStatus();
            if (offlineOrdersCount > 0) {
                syncOrders();
            }
        });

        window.addEventListener('offline', () => {
            isOnline = false;
            updateConnectionStatus();
        });

        // Initialize
        updateConnectionStatus();
        checkConnection();

        // Check for offline orders
        if ('serviceWorker' in navigator && 'indexedDB' in window) {
            // This would be handled by the service worker
            // For now, we'll simulate checking offline orders
            setTimeout(() => {
                offlineOrdersCount = Math.floor(Math.random() * 5); // Simulate offline orders
                document.getElementById('offlineOrdersCount').textContent = offlineOrdersCount;
            }, 1000);
        }
    </script>
</body>
</html> 