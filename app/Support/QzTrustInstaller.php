<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class QzTrustInstaller
{
    public static function certificatePath(): string
    {
        return config('qz.certificate_path');
    }

    public static function rootCertificatePath(): string
    {
        return config('qz.root_certificate_path');
    }

    public static function keysExist(): bool
    {
        $cert = self::certificatePath();
        $key = config('qz.private_key_path');
        $root = self::rootCertificatePath();

        return is_readable($cert) && is_readable($key) && is_readable($root);
    }

    public static function buildWindowsBatch(): string
    {
        return implode("\r\n", [
            '@echo off',
            'setlocal',
            'title QZ Tray Trust Installer',
            'echo.',
            'echo ========================================',
            'echo   QZ Tray Trust Certificate Installer',
            'echo   Run as Administrator',
            'echo ========================================',
            'echo.',
            '',
            'set "QZ_DIR=C:\Program Files\QZ Tray"',
            'set "QZ_DIR_X86=C:\Program Files (x86)\QZ Tray"',
            'set "TARGET="',
            '',
            'if exist "%QZ_DIR%\qz-tray.jar" set "TARGET=%QZ_DIR%"',
            'if "%TARGET%"=="" if exist "%QZ_DIR_X86%\qz-tray.jar" set "TARGET=%QZ_DIR_X86%"',
            '',
            'if "%TARGET%"=="" goto :no_qz',
            'if not exist "%~dp0root-certificate.crt" goto :no_cert',
            '',
            'echo Copying ROOT certificate to:',
            'echo %TARGET%\override.crt',
            'copy /Y "%~dp0root-certificate.crt" "%TARGET%\override.crt"',
            'if errorlevel 1 goto :copy_failed',
            '',
            'echo.',
            'echo SUCCESS. Root certificate installed.',
            'echo.',
            'echo Next steps:',
            'echo 1. Right-click QZ Tray icon in system tray - Exit',
            'echo 2. Start QZ Tray again from Start menu',
            'echo 3. Try printing an invoice',
            'echo.',
            'goto :end',
            '',
            ':no_qz',
            'echo ERROR: QZ Tray not found.',
            'echo Install from https://qz.io/download/',
            'goto :end',
            '',
            ':no_cert',
            'echo ERROR: root-certificate.crt not found in this folder.',
            'goto :end',
            '',
            ':copy_failed',
            'echo ERROR: Could not copy certificate.',
            'echo Run this file as Administrator (right-click - Run as administrator).',
            '',
            ':end',
            'pause',
            'endlocal',
        ])."\r\n";
    }

    public static function writeInstallerFiles(string $directory): array
    {
        File::ensureDirectoryExists($directory);

        $rootPath = self::rootCertificatePath();
        $batPath = $directory.DIRECTORY_SEPARATOR.'install-qz-trust.bat';
        $rootDest = $directory.DIRECTORY_SEPARATOR.'root-certificate.crt';

        File::copy($rootPath, $rootDest);
        File::put($batPath, self::buildWindowsBatch());

        return [
            'bat' => $batPath,
            'root' => $rootDest,
        ];
    }
}
