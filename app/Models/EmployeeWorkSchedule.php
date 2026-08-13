<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkSchedule extends Model
{
    public const DAY_LABELS = [
        0 => 'الأحد',
        1 => 'الإثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    protected $fillable = [
        'employee_id',
        'day_of_week',
        'is_working',
        'expected_checkin_time',
        'expected_checkout_time',
        'grace_minutes',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_working' => 'boolean',
        'grace_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function formattedExpectedCheckinTime(): ?string
    {
        return $this->formatTime($this->expected_checkin_time);
    }

    public function formattedExpectedCheckoutTime(): ?string
    {
        return $this->formatTime($this->expected_checkout_time);
    }

    private function formatTime(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        $parts = explode(':', $time);

        return sprintf('%02d:%02d', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0));
    }
}
