<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfflineCache extends Model
{
    use HasFactory;

    protected $table = 'offline_cache';

    protected $fillable = [
        'user_id',
        'cache_key',
        'cache_data',
    ];

    protected $casts = [
        'cache_data' => 'array',
    ];

    /**
     * العلاقة مع المستخدم
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * الحصول على بيانات مخزنة مؤقتاً
     */
    public static function get($userId, $key, $default = null)
    {
        $cache = static::where('user_id', $userId)
            ->where('cache_key', $key)
            ->first();

        return $cache ? $cache->cache_data : $default;
    }

    /**
     * تخزين بيانات مؤقتاً
     */
    public static function set($userId, $key, $data)
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'cache_key' => $key],
            ['cache_data' => $data, 'updated_at' => now()]
        );
    }

    /**
     * حذف بيانات مخزنة مؤقتاً
     */
    public static function forget($userId, $key)
    {
        return static::where('user_id', $userId)
            ->where('cache_key', $key)
            ->delete();
    }

    /**
     * حذف جميع البيانات المخزنة مؤقتاً للمستخدم
     */
    public static function clear($userId)
    {
        return static::where('user_id', $userId)->delete();
    }

    /**
     * الحصول على جميع البيانات المخزنة مؤقتاً للمستخدم
     */
    public static function getAll($userId)
    {
        return static::where('user_id', $userId)
            ->pluck('cache_data', 'cache_key')
            ->toArray();
    }

    /**
     * التحقق من وجود بيانات مخزنة مؤقتاً
     */
    public static function has($userId, $key)
    {
        return static::where('user_id', $userId)
            ->where('cache_key', $key)
            ->exists();
    }

    /**
     * الحصول على حجم البيانات المخزنة مؤقتاً للمستخدم
     */
    public static function getSize($userId)
    {
        return static::where('user_id', $userId)->count();
    }
} 