import JsBarcode from 'jsbarcode';

/** مقاس الاستيكر الفعلي: عرض × ارتفاع */
export const STICKER_WIDTH_MM = 38;
export const STICKER_HEIGHT_MM = 25;

function clearSvg(el) {
    while (el?.firstChild) {
        el.removeChild(el.firstChild);
    }
}

/** معاينة على الشاشة — واضحة للعين */
export function renderPreviewBarcode(el, code) {
    if (!el || !code) return;
    try {
        clearSvg(el);
        JsBarcode(el, code, {
            format: 'CODE128',
            width: 2,
            height: 72,
            displayValue: false,
            margin: 8,
        });
    } catch (e) {
        console.error(e);
    }
}

/**
 * باركود للطباعة على استيكر 38×25mm
 * لا تُثبّت الارتفاع في CSS بعد الرسم — يُشوّه الخطوط ويفشل الماسح
 */
export function renderPrintBarcode(el, code) {
    if (!el || !code) return;
    try {
        clearSvg(el);
        JsBarcode(el, code, {
            format: 'CODE128',
            width: 1,
            height: 32,
            displayValue: false,
            margin: 6,
        });
        el.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        el.style.width = '100%';
        el.style.height = 'auto';
        el.style.maxHeight = '12mm';
    } catch (e) {
        console.error(e);
    }
}
