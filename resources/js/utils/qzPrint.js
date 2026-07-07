let qzLoadPromise = null;
let connectPromise = null;
let securityConfigured = false;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function loadQzScript() {
    if (typeof window !== 'undefined' && window.qz) {
        return Promise.resolve(window.qz);
    }

    if (qzLoadPromise) {
        return qzLoadPromise;
    }

    qzLoadPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-qz-tray]');
        if (existing) {
            existing.addEventListener('load', () => (window.qz ? resolve(window.qz) : reject(new Error('QZ Tray failed'))));
            existing.addEventListener('error', () => reject(new Error('Failed to load QZ Tray')));
            return;
        }

        const script = document.createElement('script');
        script.src = '/js/qz-tray.js';
        script.dataset.qzTray = '1';
        script.onload = () => {
            if (window.qz) {
                resolve(window.qz);
            } else {
                reject(new Error('مكتبة QZ Tray غير متوفرة.'));
            }
        };
        script.onerror = () => reject(new Error('تعذر تحميل مكتبة QZ Tray.'));
        document.head.appendChild(script);
    });

    return qzLoadPromise;
}

async function getQz() {
    return loadQzScript();
}

async function setupQzSecurity(qz) {
    if (securityConfigured) {
        return;
    }

    qz.security.setSignatureAlgorithm('SHA512');

    qz.security.setCertificatePromise((resolve, reject) => {
        fetch('/qz/certificate', {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { Accept: 'text/plain' },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('تعذر تحميل شهادة QZ. نفّذ: php artisan qz:generate-keys');
                }
                return response.text();
            })
            .then(resolve)
            .catch(reject);
    });

    qz.security.setSignaturePromise((toSign) => (resolve, reject) => {
        fetch('/qz/sign', {
            method: 'POST',
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/plain',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ request: toSign }),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('تعذر توقيع طلب QZ.');
                }
                return response.text();
            })
            .then(resolve)
            .catch(reject);
    });

    securityConfigured = true;
}

export async function ensureQzConnected() {
    const qz = await getQz();

    await setupQzSecurity(qz);

    if (qz.websocket.isActive()) {
        return qz;
    }

    if (!connectPromise) {
        connectPromise = qz.websocket.connect().finally(() => {
            connectPromise = null;
        });
    }

    await connectPromise;

    return qz;
}

export async function listQzPrinters() {
    const qz = await ensureQzConnected();
    return qz.printers.find();
}

export async function fetchInvoiceHtml(orderId, copy = 'customer') {
    const url = `/invoice-html/${orderId}?copy=${encodeURIComponent(copy)}&qz=1`;
    const response = await fetch(url, {
        headers: { Accept: 'text/html' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('تعذر تحميل محتوى الفاتورة للطباعة.');
    }

    return response.text();
}

export async function printHtmlToPrinter(printerName, html) {
    if (!printerName) {
        throw new Error('اسم الطابعة غير محدد.');
    }

    const qz = await ensureQzConnected();

    const config = qz.configs.create(printerName, {
        scaleContent: true,
    });

    const data = [{
        type: 'html',
        format: 'plain',
        data: html,
    }];

    return qz.print(config, data);
}

/**
 * @param {number|string} orderId
 * @param {{ mode: string, customer_printer: string|null, staff_printer: string|null }} settings
 */
export async function printInvoiceViaQz(orderId, settings) {
    if (settings.mode === 'dual' && settings.staff_printer) {
        const customerHtml = await fetchInvoiceHtml(orderId, 'customer');
        await printHtmlToPrinter(settings.customer_printer, customerHtml);

        const staffHtml = await fetchInvoiceHtml(orderId, 'staff');
        await printHtmlToPrinter(settings.staff_printer, staffHtml);
    } else {
        const html = await fetchInvoiceHtml(orderId, 'customer');
        await printHtmlToPrinter(settings.customer_printer, html);
    }
}

export function isQzPrintConfigured(settings) {
    return settings?.method === 'qz' && !!settings?.customer_printer;
}
