<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles; 

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'branch_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * الـ tenant الذي ينتمي إليه المستخدم
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * المستخدمون التابعون لنفس الـ tenant (عندما يكون المستخدم سوبر أدمن)
     */
    public function tenantUsers()
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    /**
     * العلاقة مع ورديات الكاشير
     */
    public function cashierShifts()
    {
        return $this->hasMany(CashierShift::class);
    }

    public const ROLE_HR = 'hr';

    /**
     * مسؤول الموظفين فقط: متابعة الحضور والخصم على كل الفروع، بدون باقي النظام.
     */
    public function isHrOnly(): bool
    {
        return $this->hasRole(self::ROLE_HR)
            && ! $this->hasAnyRole(['admin', 'super admin', 'cashier']);
    }

    public static function rolesSkipBranchAssignment(iterable $roles): bool
    {
        $roles = collect($roles);
        if ($roles->contains('super admin')) {
            return true;
        }

        return $roles->contains(self::ROLE_HR) && $roles->diff([self::ROLE_HR])->isEmpty();
    }

    public function canViewEmployeesAcrossBranches(): bool
    {
        return $this->hasRole('super admin') || $this->isHrOnly();
    }

    /**
     * الحصول على الوردية النشطة للمستخدم
     */
    public function getActiveShift()
    {
        return $this->cashierShifts()
            ->where('status', 'active')
            ->first();
    }

    
}
