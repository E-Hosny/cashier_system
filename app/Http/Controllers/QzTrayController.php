<?php

namespace App\Http\Controllers;

use App\Support\QzTrustInstaller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use ZipArchive;

class QzTrayController extends Controller
{
    public function certificate(): Response
    {
        $path = config('qz.certificate_path');

        if (! is_readable($path)) {
            abort(HttpResponse::HTTP_SERVICE_UNAVAILABLE, 'شهادة QZ غير موجودة. نفّذ: php artisan qz:generate-keys');
        }

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function sign(Request $request): Response
    {
        $request->validate([
            'request' => 'required|string',
        ]);

        $keyPath = config('qz.private_key_path');

        if (! is_readable($keyPath)) {
            abort(HttpResponse::HTTP_SERVICE_UNAVAILABLE, 'مفتاح QZ غير موجود. نفّذ: php artisan qz:generate-keys');
        }

        $privateKey = openssl_pkey_get_private(file_get_contents($keyPath));

        if ($privateKey === false) {
            abort(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'تعذر قراءة مفتاح QZ.');
        }

        $toSign = $request->input('request');
        $signature = '';

        if (! openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA512)) {
            abort(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'فشل توقيع طلب QZ.');
        }

        return response(base64_encode($signature), 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function downloadTrustPackage(): Response
    {
        if (! QzTrustInstaller::keysExist()) {
            abort(HttpResponse::HTTP_SERVICE_UNAVAILABLE, 'مفاتيح QZ غير موجودة.');
        }

        $tempDir = storage_path('app/temp/qz-trust-'.uniqid());
        $files = QzTrustInstaller::writeInstallerFiles($tempDir);

        $zipPath = $tempDir.'.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'تعذر إنشاء الحزمة.');
        }

        $zip->addFile($files['bat'], 'install-qz-trust.bat');
        $zip->addFile($files['root'], 'root-certificate.crt');
        $zip->addFromString('اقرأني.txt', $this->trustPackageReadme());
        $zip->close();

        $contents = file_get_contents($zipPath);

        @unlink($zipPath);
        @unlink($files['bat']);
        @unlink($files['root']);
        @rmdir($tempDir);

        return response($contents, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="qz-trust-installer.zip"',
        ]);
    }

    private function trustPackageReadme(): string
    {
        return <<<'TXT'
تثبيت ثقة QZ Tray (مرة واحدة على كل جهاز كاشير)
===============================================

1. فك الضغط عن الملفات في مجلد واحد
2. انقر يميناً على install-qz-trust.bat
3. اختر: Run as administrator (تشغيل كمسؤول)
4. إذا ظهر تحذير أمان Windows اضغط Run
5. انتظر رسالة SUCCESS
6. أعد تشغيل QZ Tray:
   - يمين على الأيقونة في شريط المهام > Exit
   - افتح QZ Tray من قائمة ابدأ
7. جرّب طباعة فاتورة

إذا فشل الملف .bat انسخ يدوياً:
- انسخ root-certificate.crt
- الصقه في: C:\Program Files\QZ Tray\override.crt
  (يحتاج صلاحيات مسؤول)
- أعد تشغيل QZ Tray

ملاحظة: لا تنسخ digital-certificate.txt إلى override.crt

بديل: QZ Tray > Advanced > Site Manager > + > Create New
ثم ارفع الملفين من مجلد QZ Tray Demo Cert في الإعدادات.
TXT;
    }
}
