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
        this.connectionTestUrl = '/offline/check-connection';
        this.lastConnectionCheck = 0;
        this.connectionCheckTimeout = 5000; // 5 ثوانٍ
        
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
        
        // تحميل الطلبات المعلقة المحفوظة
        this.loadPendingRequests();
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
        // تجنب فحص الاتصال بشكل متكرر جداً
        const now = Date.now();
        if (now - this.lastConnectionCheck < 5000) {
            return;
        }
        
        this.lastConnectionCheck = now;
        
        try {
            // فحص شامل للاتصال
            const connectionStatus = await this.comprehensiveConnectionCheck();
            const wasOffline = !this.isOnline;
            
            this.isOnline = connectionStatus.isOnline;
            
            // إذا كان متصل الآن وكان غير متصل سابقاً
            if (this.isOnline && wasOffline) {
                console.log('تم استعادة الاتصال - بدء المزامنة التلقائية');
                this.syncPendingRequests();
            }
            
            // إذا كان غير متصل الآن وكان متصل سابقاً
            if (!this.isOnline && !wasOffline) {
                console.log('انقطع الاتصال - إيقاف المزامنة');
            }
            
            // تسجيل سبب عدم الاتصال إذا كان هناك مشكلة
            if (!this.isOnline && connectionStatus.reason) {
                console.log('سبب عدم الاتصال:', connectionStatus.reason);
            }
        } catch (error) {
            console.log('فشل في فحص الاتصال:', error.name, error.message);
            this.isOnline = false;
        }
    }

    // فحص شامل لحالة الاتصال - يحل مشكلة Network Offline
    async comprehensiveConnectionCheck() {
        const result = {
            isOnline: false,
            reason: '',
            details: {}
        };

        try {
            // 1. فحص حالة المتصفح الأساسية أولاً
            if (!navigator.onLine) {
                result.reason = 'navigator.onLine = false';
                console.log('المتصفح يبلغ عن عدم الاتصال');
                return result;
            }

            // 2. فحص إضافي لحالة الاتصال قبل إرسال الطلب
            if (!window.navigator.connection && !navigator.onLine) {
                result.reason = 'browser_offline';
                console.log('المتصفح في وضع عدم الاتصال');
                return result;
            }

            // 3. محاولة فحص الاتصال بالخادم مع timeout قصير جداً
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 1500); // timeout قصير جداً
                
                const response = await fetch(this.connectionTestUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache'
                    },
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (response.ok) {
                    const data = await response.json();
                    result.isOnline = data.isOnline;
                    result.details.serverResponse = data;
                    result.reason = 'server_ok';
                } else {
                    result.reason = `server_error_${response.status}`;
                    result.details.status = response.status;
                }
            } catch (fetchError) {
                // إذا فشل fetch، فهذا يعني عدم الاتصال
                console.log('فشل في إرسال طلب fetch:', fetchError.name, fetchError.message);
                
                // تحديد سبب الفشل بدقة
                if (fetchError.name === 'AbortError') {
                    result.reason = 'timeout';
                } else if (fetchError.code === 'NS_ERROR_OFFLINE') {
                    result.reason = 'ns_error_offline';
                } else if (fetchError.code === 'ERR_NETWORK') {
                    result.reason = 'err_network';
                } else if (fetchError.code === 'ERR_INTERNET_DISCONNECTED') {
                    result.reason = 'err_internet_disconnected';
                } else if (fetchError.message.includes('Network Error')) {
                    result.reason = 'network_error';
                } else if (fetchError.message.includes('Failed to fetch')) {
                    result.reason = 'failed_to_fetch';
                } else {
                    result.reason = 'fetch_failed';
                }
                
                result.details.error = {
                    name: fetchError.name,
                    message: fetchError.message,
                    code: fetchError.code
                };
            }
        } catch (error) {
            console.log('خطأ عام في الفحص الشامل للاتصال:', error.name, error.message);
            result.reason = 'general_error';
            result.details.error = {
                name: error.name,
                message: error.message,
                code: error.code
            };
        }

        console.log('نتيجة الفحص الشامل:', result);
        return result;
    }

    // فحص سريع للاتصال بدون timeout طويل
    async quickConnectionCheck() {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 2000); // timeout قصير
            
            const response = await fetch(this.connectionTestUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (response.ok) {
                const data = await response.json();
                return data.isOnline;
            }
            return false;
        } catch (error) {
            console.log('فشل في الفحص السريع للاتصال:', error.message);
            return false;
        }
    }

    interceptAxiosRequests() {
        // اعتراض طلبات axios للتعامل مع انقطاع الاتصال
        if (window.axios && typeof window.axios.interceptors !== 'undefined') {
            // اعتراض الطلبات الصادرة
            window.axios.interceptors.request.use(
                (config) => {
                    // إضافة timestamp للطلب
                    config.metadata = { startTime: new Date() };
                    
                    // إذا كان الطلب إلى مسار أوفلاين، لا نحتاج للتحقق من الاتصال
                    if (config.url && config.url.includes('/offline/')) {
                        return config;
                    }
                    
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
                    if (this.isNetworkError(error)) {
                        console.log('خطأ شبكة تم اكتشافه:', error.message);
                        
                        // تحديث حالة الاتصال
                        this.isOnline = false;
                        
                        // حفظ الطلب للمحاولة لاحقاً (فقط إذا لم يكن طلب أوفلاين)
                        if (error.config && !error.config.url.includes('/offline/')) {
                            this.addPendingRequest(error.config);
                            this.showNotification('تم حفظ الطلب للمزامنة عند عودة الاتصال', 'info');
                        }
                    }
                    
                    return Promise.reject(error);
                }
            );
        } else {
            console.warn('axios غير متاح لاعتراض الطلبات');
        }
    }

    // فحص ما إذا كان الخطأ خطأ شبكة
    isNetworkError(error) {
        // فحص حالة المتصفح أولاً
        if (!navigator.onLine) {
            return true;
        }
        
        // فحص أنواع الأخطاء المختلفة
        return error.code === 'NETWORK_ERROR' || 
               error.message.includes('Network Error') ||
               error.code === 'ERR_NETWORK' ||
               error.code === 'NS_ERROR_OFFLINE' ||
               error.code === 'ERR_INTERNET_DISCONNECTED' ||
               error.name === 'AbortError' ||
               error.message.includes('Failed to fetch') ||
               error.message.includes('Network request failed') ||
               error.message.includes('ERR_CONNECTION_REFUSED') ||
               error.message.includes('ERR_NAME_NOT_RESOLVED') ||
               error.message.includes('ERR_INTERNET_DISCONNECTED') ||
               error.message.includes('ERR_NETWORK_CHANGED') ||
               error.message.includes('ERR_NETWORK_ACCESS_DENIED') ||
               error.message.includes('ERR_CONNECTION_TIMED_OUT') ||
               error.message.includes('ERR_CONNECTION_RESET') ||
               error.message.includes('ERR_CONNECTION_ABORTED') ||
               error.message.includes('ERR_CONNECTION_CLOSED') ||
               error.message.includes('ERR_CONNECTION_FAILED') ||
               error.message.includes('ERR_CONNECTION_REFUSED') ||
               error.message.includes('ERR_CONNECTION_RESET') ||
               error.message.includes('ERR_CONNECTION_TIMED_OUT') ||
               error.message.includes('ERR_CONNECTION_ABORTED') ||
               error.message.includes('ERR_CONNECTION_CLOSED') ||
               error.message.includes('ERR_CONNECTION_FAILED');
    }

    addPendingRequest(config) {
        // التأكد من وجود config صحيح
        if (!config || !config.url) {
            console.error('محاولة إضافة طلب غير صحيح:', config);
            return;
        }

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

        // التأكد من وجود axios
        if (!window.axios) {
            console.error('axios غير متاح للمزامنة');
            return;
        }

        console.log(`محاولة مزامنة ${this.pendingRequests.length} طلب معلق`);
        
        const requestsToProcess = [...this.pendingRequests];
        this.pendingRequests = [];

        for (const request of requestsToProcess) {
            try {
                // التأكد من وجود config صحيح
                if (!request.config || !request.config.url) {
                    console.error('طلب غير صحيح، تخطي:', request);
                    continue;
                }

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
            // التأكد من صحة البيانات قبل الحفظ
            const validRequests = this.pendingRequests.filter(request => 
                request && request.config && request.config.url
            );
            
            localStorage.setItem('offline_pending_requests', JSON.stringify(validRequests));
        } catch (error) {
            console.error('فشل في حفظ الطلبات المعلقة:', error);
        }
    }

    loadPendingRequests() {
        try {
            const saved = localStorage.getItem('offline_pending_requests');
            if (saved) {
                const parsed = JSON.parse(saved);
                
                // التأكد من صحة البيانات المحفوظة
                if (Array.isArray(parsed)) {
                    this.pendingRequests = parsed.filter(request => 
                        request && request.config && request.config.url
                    );
                    console.log(`تم تحميل ${this.pendingRequests.length} طلب معلق صحيح`);
                } else {
                    console.warn('بيانات الطلبات المعلقة غير صحيحة، تم تجاهلها');
                    this.pendingRequests = [];
                }
            }
        } catch (error) {
            console.error('فشل في تحميل الطلبات المعلقة:', error);
            this.pendingRequests = [];
        }
    }

    showNotification(message, type = 'info') {
        // إنشاء عنصر الإشعار
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all duration-300 ${
            type === 'success' ? 'bg-green-500 text-white' : 
            type === 'error' ? 'bg-red-500 text-white' : 
            type === 'warning' ? 'bg-yellow-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">×</button>
            </div>
        `;
        
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
            lastCheck: this.lastConnectionCheck
        };
    }

    destroy() {
        this.stopConnectionCheck();
        window.removeEventListener('online', this.handleOnline);
        window.removeEventListener('offline', this.handleOffline);
    }
}

// تصدير الكلاس للاستخدام العام
window.OfflineManager = OfflineManager; 