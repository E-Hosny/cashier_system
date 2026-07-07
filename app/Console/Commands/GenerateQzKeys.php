<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class GenerateQzKeys extends Command
{
    protected $signature = 'qz:generate-keys {--force : إعادة إنشاء المفاتيح حتى لو كانت موجودة}';

    protected $description = 'إنشاء شهادة CA + شهادة موقّعة ومفتاح خاص لتوقيع طلبات QZ Tray';

    public function handle(): int
    {
        $certPath = config('qz.certificate_path');
        $keyPath = config('qz.private_key_path');
        $rootPath = config('qz.root_certificate_path');
        $dir = dirname($certPath);

        if (! $this->option('force') && is_readable($certPath) && is_readable($keyPath) && is_readable($rootPath)) {
            $this->info('مفاتيح QZ موجودة مسبقاً. استخدم --force لإعادة الإنشاء.');

            return self::SUCCESS;
        }

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $openssl = $this->findOpenSslBinary();

        if ($openssl === null) {
            $this->error('لم يتم العثور على OpenSSL. ثبّته أو أضفه إلى PATH.');

            return self::FAILURE;
        }

        $org = config('qz.organization');
        $days = (string) config('qz.key_days');
        $caConfig = $this->writeConfig($dir, 'openssl-ca.cnf', true);
        $leafConfig = $this->writeConfig($dir, 'openssl-leaf.cnf', false);

        $caKey = $dir.DIRECTORY_SEPARATOR.'ca-key.pem';
        $leafCsr = $dir.DIRECTORY_SEPARATOR.'leaf.csr';
        $caSerial = $dir.DIRECTORY_SEPARATOR.'ca.srl';

        $caSubject = '/C=EG/O='.str_replace('/', '\/', $org).'/CN=QZ Tray Root';
        $leafSubject = '/C=EG/O='.str_replace('/', '\/', $org).'/CN=QZ Tray';

        $steps = [
            [$openssl, 'genrsa', '-out', $caKey, '2048'],
            [$openssl, 'req', '-x509', '-new', '-nodes', '-key', $caKey, '-sha256', '-days', $days,
                '-out', $rootPath, '-subj', $caSubject, '-config', $caConfig],
            [$openssl, 'genrsa', '-out', $keyPath, '2048'],
            [$openssl, 'req', '-new', '-key', $keyPath, '-out', $leafCsr, '-subj', $leafSubject, '-config', $leafConfig],
            [$openssl, 'x509', '-req', '-in', $leafCsr, '-CA', $rootPath, '-CAkey', $caKey,
                '-CAcreateserial', '-out', $certPath, '-days', $days, '-sha256', '-extfile', $leafConfig, '-extensions', 'v3_leaf'],
        ];

        foreach ($steps as $args) {
            $process = new Process($args);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error('فشل إنشاء مفاتيح QZ.');
                if ($process->getErrorOutput()) {
                    $this->line($process->getErrorOutput());
                }

                return self::FAILURE;
            }
        }

        @unlink($caKey);
        @unlink($leafCsr);
        @unlink($caSerial);
        @unlink($caConfig);
        @unlink($leafConfig);

        $this->info('تم إنشاء مفاتيح QZ (CA + شهادة موقّعة):');
        $this->line("  شهادة الموقع (للسيرفر): {$certPath}");
        $this->line("  شهادة الجذر (لـ override.crt): {$rootPath}");
        $this->line("  المفتاح الخاص: {$keyPath}");
        $this->newLine();
        $this->warn('على جهاز الكاشير: حمّل حزمة التثبيت من الإعدادات وشغّل install-qz-trust.bat كمسؤول.');

        return self::SUCCESS;
    }

    private function writeConfig(string $dir, string $filename, bool $isCa): string
    {
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        if ($isCa) {
            $content = <<<'CONF'
[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name
x509_extensions = v3_ca
prompt = no

[ req_distinguished_name ]

[ v3_ca ]
basicConstraints = critical, CA:true
keyUsage = critical, digitalSignature, keyCertSign, cRLSign
CONF;
        } else {
            $content = <<<'CONF'
[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name
req_extensions = v3_leaf
prompt = no

[ req_distinguished_name ]

[ v3_leaf ]
basicConstraints = CA:FALSE
keyUsage = digitalSignature, keyEncipherment
extendedKeyUsage = clientAuth
CONF;
        }

        File::put($path, $content);

        return $path;
    }

    private function findOpenSslBinary(): ?string
    {
        $candidates = array_filter([
            env('OPENSSL_BIN'),
            'C:\\xampp\\apache\\bin\\openssl.exe',
            'C:\\xampp\\php\\extras\\openssl\\openssl.exe',
            'openssl',
        ]);

        foreach ($candidates as $candidate) {
            $process = new Process([$candidate, 'version']);
            $process->run();

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        return null;
    }
}
