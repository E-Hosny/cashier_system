<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SpacesLabController extends Controller
{
    private const PREFIX = 'playground';

    private const DISK = 'spaces';

    public function index(): Response
    {
        abort_unless(Auth::user()?->hasRole('super admin'), 403);

        $diskConfigured = $this->diskConfigured();
        $files = [];
        $error = null;

        if ($diskConfigured) {
            try {
                $disk = Storage::disk(self::DISK);
                foreach ($disk->files(self::PREFIX) as $path) {
                    $files[] = $this->mapFile($disk, $path);
                }

                usort($files, fn ($a, $b) => strcmp($b['last_modified'] ?? '', $a['last_modified'] ?? ''));
            } catch (Throwable $e) {
                $error = 'تعذر الاتصال بـ Spaces: '.$e->getMessage();
            }
        } else {
            $error = 'إعدادات DigitalOcean Spaces غير مكتملة في ملف .env';
        }

        return Inertia::render('Admin/SpacesLab/Index', [
            'files' => $files,
            'diskConfigured' => $diskConfigured,
            'error' => $error,
            'config' => [
                'disk' => self::DISK,
                'prefix' => self::PREFIX,
                'region' => config('filesystems.disks.spaces.region'),
                'bucket' => config('filesystems.disks.spaces.bucket'),
                'endpoint' => config('filesystems.disks.spaces.endpoint'),
                'url' => config('filesystems.disks.spaces.url'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('super admin'), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'name' => ['nullable', 'string', 'max:180'],
        ]);

        try {
            $disk = Storage::disk(self::DISK);
            $path = $this->buildPath($request->file('file')->getClientOriginalExtension(), $request->input('name'));

            $stored = $disk->putFileAs(
                self::PREFIX,
                $request->file('file'),
                basename($path),
                ['visibility' => 'public']
            );

            if (! $stored) {
                return back()->withErrors(['file' => 'فشل رفع الملف إلى Spaces.']);
            }

            return redirect()
                ->route('admin.spaces-lab.index')
                ->with('success', 'تم رفع الملف بنجاح: '.$stored);
        } catch (Throwable $e) {
            return back()->withErrors(['file' => 'خطأ Spaces: '.$e->getMessage()]);
        }
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('super admin'), 403);

        $validated = $request->validate([
            'path' => ['required', 'string'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $path = $this->assertPlaygroundPath($validated['path']);

        try {
            $disk = Storage::disk(self::DISK);

            if (! $disk->exists($path)) {
                return back()->withErrors(['file' => 'الملف غير موجود.']);
            }

            $extension = $request->file('file')->getClientOriginalExtension()
                ?: pathinfo($path, PATHINFO_EXTENSION);
            $newName = pathinfo($path, PATHINFO_FILENAME).'.'.$extension;
            $newPath = self::PREFIX.'/'.$newName;

            $stored = $disk->putFileAs(
                self::PREFIX,
                $request->file('file'),
                $newName,
                ['visibility' => 'public']
            );

            if (! $stored) {
                return back()->withErrors(['file' => 'فشل استبدال الملف.']);
            }

            if ($newPath !== $path) {
                $disk->delete($path);
            }

            return redirect()
                ->route('admin.spaces-lab.index')
                ->with('success', 'تم استبدال الملف بنجاح.');
        } catch (Throwable $e) {
            return back()->withErrors(['file' => 'خطأ Spaces: '.$e->getMessage()]);
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('super admin'), 403);

        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $path = $this->assertPlaygroundPath($validated['path']);

        try {
            $disk = Storage::disk(self::DISK);

            if ($disk->exists($path)) {
                $disk->delete($path);
            }

            return redirect()
                ->route('admin.spaces-lab.index')
                ->with('success', 'تم حذف الملف.');
        } catch (Throwable $e) {
            return back()->withErrors(['file' => 'خطأ Spaces: '.$e->getMessage()]);
        }
    }

    private function diskConfigured(): bool
    {
        $config = config('filesystems.disks.spaces');

        return filled($config['key'] ?? null)
            && filled($config['secret'] ?? null)
            && filled($config['bucket'] ?? null)
            && filled($config['endpoint'] ?? null)
            && filled($config['url'] ?? null);
    }

    private function buildPath(?string $extension, ?string $customName): string
    {
        $extension = strtolower(ltrim((string) $extension, '.'));
        $base = $customName
            ? Str::slug(pathinfo($customName, PATHINFO_FILENAME) ?: $customName)
            : now()->format('Ymd_His').'_'.Str::random(6);

        if ($base === '') {
            $base = 'file_'.Str::random(8);
        }

        return self::PREFIX.'/'.$base.($extension ? '.'.$extension : '');
    }

    private function assertPlaygroundPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        abort_unless(Str::startsWith($path, self::PREFIX.'/'), 422, 'مسار غير مسموح.');
        abort_if(str_contains($path, '..'), 422, 'مسار غير مسموح.');

        return $path;
    }

    private function mapFile($disk, string $path): array
    {
        $mime = null;
        $size = null;
        $lastModified = null;

        try {
            $size = $disk->size($path);
        } catch (Throwable) {
        }

        try {
            $mime = $disk->mimeType($path);
        } catch (Throwable) {
        }

        try {
            $ts = $disk->lastModified($path);
            $lastModified = $ts ? date('Y-m-d H:i:s', $ts) : null;
        } catch (Throwable) {
        }

        $url = $disk->url($path);
        $isImage = is_string($mime) && str_starts_with($mime, 'image/');

        if (! $isImage) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
        }

        return [
            'path' => $path,
            'name' => basename($path),
            'url' => $url,
            'size' => $size,
            'size_label' => $size !== null ? $this->formatBytes((int) $size) : '—',
            'mime' => $mime,
            'is_image' => $isImage,
            'last_modified' => $lastModified,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 2).' MB';
    }
}
