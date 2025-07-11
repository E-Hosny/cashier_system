<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار النظام بدون إنترنت</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            color: white;
        }
        .test-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .status {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
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
        .test-result {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin: 10px 0;
        }
        .success {
            border-left: 4px solid #4CAF50;
        }
        .error {
            border-left: 4px solid #f44336;
        }
    </style>
</head>
<body>
    <div class="connection-status" id="connectionStatus">
        <span id="statusText">جاري التحقق...</span>
    </div>

    <div class="test-container">
        <h1>🧪 اختبار النظام بدون إنترنت</h1>
        <p>هذه الصفحة لاختبار وظائف النظام عند انقطاع الإنترنت</p>
        
        <div class="status">
            <strong>حالة الاتصال:</strong>
            <span id="connectionInfo">جاري التحقق...</span>
        </div>

        <div>
            <button class="btn" onclick="testConnection()">فحص الاتصال</button>
            <button class="btn" onclick="testOfflineInvoice()">اختبار طباعة فاتورة محلية</button>
            <button class="btn" onclick="testServiceWorker()">اختبار Service Worker</button>
            <button class="btn" onclick="testIndexedDB()">اختبار IndexedDB</button>
            <button class="btn" onclick="goToCashier()">العودة للكاشير</button>
        </div>

        <div id="testResults"></div>
    </div>

    <script>
        let isOnline = navigator.onLine;

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

        // Test connection
        async function testConnection() {
            addTestResult('فحص الاتصال', 'جاري الفحص...', '');
            
            try {
                const response = await fetch('/api/offline/health-check', { 
                    method: 'HEAD',
                    cache: 'no-cache',
                    timeout: 3000
                });
                
                if (response.ok) {
                    isOnline = true;
                    updateConnectionStatus();
                    addTestResult('فحص الاتصال', 'نجح', 'success');
                } else {
                    throw new Error('Response not ok');
                }
            } catch (error) {
                isOnline = false;
                updateConnectionStatus();
                addTestResult('فحص الاتصال', 'فشل - لا يوجد اتصال', 'error');
            }
        }

        // Test offline invoice printing
        function testOfflineInvoice() {
            addTestResult('طباعة فاتورة محلية', 'جاري الاختبار...', '');
            
            const testOrderData = {
                offline_id: `test_${Date.now()}`,
                total_price: 150.00,
                items: [
                    {
                        product_name: 'قهوة تركية',
                        quantity: 2,
                        price: 25.00,
                        size: 'متوسط'
                    },
                    {
                        product_name: 'شاي بالنعناع',
                        quantity: 1,
                        price: 15.00,
                        size: 'صغير'
                    }
                ]
            };

            try {
                // إنشاء iframe للطباعة
                const printIframe = document.createElement('iframe');
                printIframe.style.position = 'fixed';
                printIframe.style.top = '-9999px';
                printIframe.style.left = '-9999px';
                printIframe.style.width = '320px';
                printIframe.style.height = '500px';
                document.body.appendChild(printIframe);
                
                const invoiceHTML = generateTestInvoiceHTML(testOrderData);
                const iframeDoc = printIframe.contentDocument || printIframe.contentWindow.document;
                iframeDoc.open();
                iframeDoc.write(invoiceHTML);
                iframeDoc.close();
                
                setTimeout(() => {
                    try {
                        printIframe.contentWindow.focus();
                        printIframe.contentWindow.print();
                        addTestResult('طباعة فاتورة محلية', 'نجح - تم فتح نافذة الطباعة', 'success');
                        
                        setTimeout(() => {
                            document.body.removeChild(printIframe);
                        }, 2000);
                    } catch (error) {
                        addTestResult('طباعة فاتورة محلية', 'فشل في الطباعة: ' + error.message, 'error');
                        document.body.removeChild(printIframe);
                    }
                }, 500);
            } catch (error) {
                addTestResult('طباعة فاتورة محلية', 'فشل: ' + error.message, 'error');
            }
        }

        // Test Service Worker
        async function testServiceWorker() {
            addTestResult('Service Worker', 'جاري الاختبار...', '');
            
            if ('serviceWorker' in navigator) {
                try {
                    const registration = await navigator.serviceWorker.getRegistration();
                    if (registration) {
                        addTestResult('Service Worker', 'مُسجل ويعمل', 'success');
                    } else {
                        addTestResult('Service Worker', 'غير مُسجل', 'error');
                    }
                } catch (error) {
                    addTestResult('Service Worker', 'فشل في الفحص: ' + error.message, 'error');
                }
            } else {
                addTestResult('Service Worker', 'غير مدعوم في هذا المتصفح', 'error');
            }
        }

        // Test IndexedDB
        async function testIndexedDB() {
            addTestResult('IndexedDB', 'جاري الاختبار...', '');
            
            if ('indexedDB' in window) {
                try {
                    const db = await openTestDB();
                    addTestResult('IndexedDB', 'يعمل بشكل صحيح', 'success');
                } catch (error) {
                    addTestResult('IndexedDB', 'فشل: ' + error.message, 'error');
                }
            } else {
                addTestResult('IndexedDB', 'غير مدعوم في هذا المتصفح', 'error');
            }
        }

        // Open test IndexedDB
        function openTestDB() {
            return new Promise((resolve, reject) => {
                const request = indexedDB.open('CashierSystem', 1);
                
                request.onerror = () => reject(request.error);
                request.onsuccess = () => resolve(request.result);
                
                request.onupgradeneeded = (event) => {
                    const db = event.target.result;
                    
                    if (!db.objectStoreNames.contains('orders')) {
                        const orderStore = db.createObjectStore('orders', { keyPath: 'id', autoIncrement: true });
                        orderStore.createIndex('timestamp', 'timestamp', { unique: false });
                    }
                };
            });
        }

        // Generate test invoice HTML
        function generateTestInvoiceHTML(orderData) {
            const now = new Date();
            const orderId = orderData.offline_id;
            const total = parseFloat(orderData.total_price);
            
            return `
                <!DOCTYPE html>
                <html lang="ar" dir="rtl">
                <head>
                    <meta charset="UTF-8">
                    <title>فاتورة اختبار</title>
                    <style>
                        @page { size: 80mm 297mm; margin: 5mm; }
                        body { 
                            font-family: Arial, sans-serif; 
                            direction: rtl; 
                            padding: 5px; 
                            margin: 0;
                            font-size: 12px;
                            max-width: 70mm;
                            margin: 0 auto;
                        }
                        table { 
                            width: 100%; 
                            border-collapse: collapse; 
                            margin-top: 10px; 
                            font-size: 11px;
                        }
                        th, td { 
                            border: 1px solid #000; 
                            padding: 4px; 
                            text-align: right; 
                            font-size: 10px;
                        }
                        th { 
                            background: #eee; 
                            font-weight: bold;
                            font-size: 11px;
                        }
                        .total { 
                            margin-top: 10px; 
                            font-weight: bold; 
                            font-size: 14px; 
                            text-align: center;
                            border-top: 2px solid #000;
                            padding-top: 5px;
                        }
                        .header {
                            text-align: center;
                            margin-bottom: 10px;
                            border-bottom: 2px solid #000;
                            padding-bottom: 10px;
                        }
                        .invoice-title {
                            font-size: 16px;
                            font-weight: bold;
                            margin: 5px 0;
                        }
                        .test-notice {
                            background: #fff3cd;
                            border: 1px solid #ffeaa7;
                            padding: 5px;
                            margin: 8px 0;
                            border-radius: 3px;
                            font-size: 9px;
                            text-align: center;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="invoice-title">فاتورة اختبار #${orderId}</div>
                        <div style="font-size: 11px; color: #666;">التاريخ: ${now.toLocaleDateString('ar-SA')} ${now.toLocaleTimeString('ar-SA')}</div>
                        <div class="test-notice">🧪 فاتورة اختبار - للنظام بدون إنترنت</div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>المنتج</th>
                                <th>الكمية</th>
                                <th>السعر</th>
                                <th>الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${orderData.items.map(item => `
                                <tr>
                                    <td>${item.product_name}${item.size ? ` (${item.size})` : ''}</td>
                                    <td>${item.quantity}</td>
                                    <td>${parseFloat(item.price).toFixed(2)}</td>
                                    <td>${(item.quantity * parseFloat(item.price)).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>

                    <div class="total">الإجمالي الكلي: ${total.toFixed(2)} جنيه</div>
                    
                    <div style="text-align: center; margin-top: 15px; font-size: 10px; color: #666;">
                        <div style="margin-bottom: 5px;">شكراً لزيارتكم 🌟</div>
                        <div style="font-size: 8px; color: #999;">فاتورة اختبار للنظام بدون إنترنت</div>
                    </div>
                </body>
                </html>
            `;
        }

        // Add test result
        function addTestResult(testName, result, type) {
            const resultsDiv = document.getElementById('testResults');
            const resultDiv = document.createElement('div');
            resultDiv.className = `test-result ${type}`;
            resultDiv.innerHTML = `<strong>${testName}:</strong> ${result}`;
            resultsDiv.appendChild(resultDiv);
        }

        // Go to cashier
        function goToCashier() {
            window.location.href = '/cashier';
        }

        // Event listeners
        window.addEventListener('online', () => {
            isOnline = true;
            updateConnectionStatus();
        });

        window.addEventListener('offline', () => {
            isOnline = false;
            updateConnectionStatus();
        });

        // Initialize
        updateConnectionStatus();
        testConnection();
    </script>
</body>
</html> 