<?php

namespace App\Console\Commands;

use App\Support\QzTrustInstaller;
use Illuminate\Console\Command;

class MakeQzTrustInstaller extends Command
{
    protected $signature = 'qz:trust-installer';

    protected $description = 'إنشاء ملف تثبيت ثقة QZ Tray لجهاز الكاشير (Windows)';

    public function handle(): int
    {
        if (! QzTrustInstaller::keysExist()) {
            $this->error('مفاتيح QZ غير موجودة. نفّذ أولاً: php artisan qz:generate-keys');

            return self::FAILURE;
        }

        $dir = dirname(config('qz.certificate_path'));
        $files = QzTrustInstaller::writeInstallerFiles($dir);

        $this->info('تم إنشاء ملفات تثبيت الثقة:');
        $this->line('  '.$files['bat']);
        $this->line('  '.$files['root']);
        $this->newLine();
        $this->warn('على جهاز الكاشير:');
        $this->line('  1. انسخ المجلد storage/app/qz/ بالكامل إلى جهاز الكاشير');
        $this->line('  2. شغّل install-qz-trust.bat كمسؤول (Run as administrator)');
        $this->line('  3. أعد تشغيل QZ Tray');

        return self::SUCCESS;
    }
}
