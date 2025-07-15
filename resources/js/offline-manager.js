/**
 * مدير حالة الاتصال في المتصفح
 * يتعامل مع انقطاع الاتصال وعودته
 */
class OfflineManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.connectionCheckInterval = null;
        this.pendingRequests = [];
        this.retryAttempts = 0;
        this.maxRetryAttempts = 3;
        
        this.init();
    }

    init() {
        // مراقبة تغييرات حالة الاتصال
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());
        
        // بدء فحص الاتصال الدوري
        this.startConnectionCheck();
        
        // اعتراض طلبات axios
        this.interceptAxiosRequests();
    }

    handleOnline() {
        console.log('تم استعادة الاتصال بالإنترنت');
        this.isOnline = true;
        this.retryAttempts = 0;
        
        // إظهار إشعار للمستخدم
        this.showNotification('تم استعادة الاتصال بالإنترنت', 'success');
        
        // محاولة مزامنة الطلبات المعلقة
        this.syncPendingRequests();
        
        // إعادة تشغيل فحص الاتصال
        this.startConnectionCheck();
    }

    handleOffline() {
        console.log('انقطع الاتصال بالإنترنت');
        this.isOnline = false;
        
        // إظهار إشعار للمستخدم
        this.showNotification('انقطع الاتصال بالإنترنت - يمكنك الاستمرار في العمل', 'warning');
        
        // إيقاف فحص الاتصال
        this.stopConnectionCheck();
    }

    startConnectionCheck() {
        if (this.connectionCheckInterval) {
            clearInterval(this.connectionCheckInterval);
        }
        
        this.connectionCheckInterval = setInterval(() => {
            this.checkConnection();
        }, 30000); // فحص كل 30 ثانية
    }

    stopConnectionCheck() {
        if (this.connectionCheckInterval) {
            clearInterval(this.connectionCheckInterval);
            this.connectionCheckInterval = null;
        }
    }

    async checkConnection() {
        try {
            const response = await fetch('/offline/check-connection', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                this.isOnline = data.isOnline;
                
                if (this.isOnline && this.pendingRequests.length > 0) {
                    this.syncPendingRequests();
                }
            }
        } catch (error) {
            console.log('فشل في فحص الاتصال:', error);
            this.isOnline = false;
        }
    }

    interceptAxiosRequests() {
        // اعتراض طلبات axios للتعامل مع انقطاع الاتصال
        if (window.axios) {
            // اعتراض الطلبات الصادرة
            window.axios.interceptors.request.use(
                (config) => {
                    // إضافة timestamp للطلب
                    config.metadata = { startTime: new Date() };
                    return config;
                },
                (error) => {
                    return Promise.reject(error);
                }
            );

            // اعتراض الاستجابات
            window.axios.interceptors.response.use(
                (response) => {
                    return response;
                },
                (error) => {
                    // إذا كان الخطأ بسبب انقطاع الاتصال
                    if (error.code === 'NETWORK_ERROR' || 
                        error.message.includes('Network Error') ||
                        !navigator.onLine) {
                        
                        // حفظ الطلب للمحاولة لاحقاً
                        this.addPendingRequest(error.config);
                        
                        // إظهار رسالة للمستخدم
                        this.showNotification('تم حفظ الطلب للمزامنة عند عودة الاتصال', 'info');
                    }
                    
                    return Promise.reject(error);
                }
            );
        }
    }

    addPendingRequest(config) {
        // إضافة الطلب إلى قائمة الطلبات المعلقة
        this.pendingRequests.push({
            config: config,
            timestamp: new Date(),
            attempts: 0
        });
        
        // حفظ الطلبات في localStorage
        this.savePendingRequests();
    }

    async syncPendingRequests() {
        if (this.pendingRequests.length === 0 || !this.isOnline) {
            return;
        }

        console.log(`محاولة مزامنة ${this.pendingRequests.length} طلب معلق`);
        
        const requestsToProcess = [...this.pendingRequests];
        this.pendingRequests = [];

        for (const request of requestsToProcess) {
            try {
                // إعادة إرسال الطلب
                const response = await window.axios(request.config);
                console.log('تم مزامنة الطلب بنجاح:', request.config.url);
            } catch (error) {
                console.error('فشل في مزامنة الطلب:', error);
                
                // إعادة إضافة الطلب إذا لم تتجاوز عدد المحاولات
                if (request.attempts < this.maxRetryAttempts) {
                    request.attempts++;
                    this.pendingRequests.push(request);
                }
            }
        }
        
        // حفظ الطلبات المحدثة
        this.savePendingRequests();
        
        if (this.pendingRequests.length === 0) {
            this.showNotification('تم مزامنة جميع الطلبات بنجاح', 'success');
        } else {
            this.showNotification(`تم مزامنة بعض الطلبات، ${this.pendingRequests.length} طلب معلق`, 'warning');
        }
    }

    savePendingRequests() {
        try {
            localStorage.setItem('offline_pending_requests', JSON.stringify(this.pendingRequests));
        } catch (error) {
            console.error('فشل في حفظ الطلبات المعلقة:', error);
        }
    }

    loadPendingRequests() {
        try {
            const saved = localStorage.getItem('offline_pending_requests');
            if (saved) {
                this.pendingRequests = JSON.parse(saved);
                console.log(`تم تحميل ${this.pendingRequests.length} طلب معلق`);
            }
        } catch (error) {
            console.error('فشل في تحميل الطلبات المعلقة:', error);
        }
    }

    showNotification(message, type = 'info') {
        // إنشاء إشعار للمستخدم
        const notification = document.createElement('div');
        notification.className = `offline-notification offline-notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // إضافة CSS للإشعار
        if (!document.getElementById('offline-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'offline-notification-styles';
            style.textContent = `
                .offline-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    padding: 15px 20px;
                    border-radius: 8px;
                    color: white;
                    font-weight: 500;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    animation: slideIn 0.3s ease-out;
                }
                .offline-notification-success { background-color: #10b981; }
                .offline-notification-warning { background-color: #f59e0b; }
                .offline-notification-error { background-color: #ef4444; }
                .offline-notification-info { background-color: #3b82f6; }
                .notification-content {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .notification-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 18px;
                    cursor: pointer;
                    padding: 0;
                    margin-left: 10px;
                }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }
        
        document.body.appendChild(notification);
        
        // إزالة الإشعار تلقائياً بعد 5 ثوانٍ
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    getConnectionStatus() {
        return {
            isOnline: this.isOnline,
            pendingRequests: this.pendingRequests.length,
            lastCheck: new Date()
        };
    }

    destroy() {
        this.stopConnectionCheck();
        window.removeEventListener('online', this.handleOnline);
        window.removeEventListener('offline', this.handleOffline);
    }
}

// إنشاء instance عالمي
window.offlineManager = new OfflineManager();

// تصدير للاستخدام في الملفات الأخرى
export default window.offlineManager; 