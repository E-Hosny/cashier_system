<?php

namespace App\Support;

/**
 * مصدر واحد لتعريف مجموعات الصلاحيات التفصيلية.
 *
 * قواعد الأمان:
 * - الإضافة فقط في المزامنة (لا حذف صلاحيات من الأدوار إلا عبر الواجهة وبشكل واعٍ).
 * - feature: إن وُجدت تُخفى المجموعة من واجهة الـ tenant إن لم تكن الميزة مفعّلة.
 * - scope: branch | central | either — للتوضيح في الواجهة؛ عزل البيانات يبقى عبر BranchContext.
 */
class PermissionCatalog
{
    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   feature: ?string,
     *   permissions: list<array{name: string, label: string, scope: string, description?: string}>
     * }>
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'products',
                'label' => 'المنتجات',
                'feature' => null,
                'permissions' => [
                    ['name' => 'products.view', 'label' => 'عرض المنتجات', 'scope' => 'either', 'description' => 'الاطلاع على قائمة المنتجات والتصدير'],
                    ['name' => 'products.manage', 'label' => 'إدارة المنتجات', 'scope' => 'central', 'description' => 'إضافة وتعديل وحذف المنتجات والمسودات'],
                    ['name' => 'products.cost_analysis', 'label' => 'تحليل تكلفة المنتجات', 'scope' => 'either'],
                    ['name' => 'products.sales_analysis', 'label' => 'تحليل مبيعات المنتجات', 'scope' => 'central'],
                    ['name' => 'categories.manage', 'label' => 'إدارة التصنيفات', 'scope' => 'central'],
                ],
            ],
            [
                'key' => 'offers',
                'label' => 'العروض',
                'feature' => null,
                'permissions' => [
                    ['name' => 'offers.view', 'label' => 'عرض العروض', 'scope' => 'central'],
                    ['name' => 'offers.manage', 'label' => 'إدارة العروض', 'scope' => 'central', 'description' => 'إضافة وتعديل وحذف العروض'],
                ],
            ],
            [
                'key' => 'inventory',
                'label' => 'المواد الخام والمخزون',
                'feature' => null,
                'permissions' => [
                    ['name' => 'inventory.view', 'label' => 'عرض المواد الخام', 'scope' => 'central'],
                    ['name' => 'inventory.manage', 'label' => 'إدارة المواد الخام', 'scope' => 'central', 'description' => 'إضافة وتعديل وحذف المواد والتصنيفات'],
                    ['name' => 'inventory.branch_pull', 'label' => 'سحب للفرع', 'scope' => 'branch'],
                    ['name' => 'inventory.fridge_pull', 'label' => 'سحب الثلاجة', 'scope' => 'branch'],
                    ['name' => 'inventory.counts', 'label' => 'جرد المخزون', 'scope' => 'central'],
                    ['name' => 'inventory.fridge_manage', 'label' => 'إدارة إعدادات الثلاجة', 'scope' => 'central'],
                ],
            ],
            [
                'key' => 'cashier',
                'label' => 'الكاشير والفواتير',
                'feature' => null,
                'permissions' => [
                    ['name' => 'cashier.use', 'label' => 'استخدام الكاشير', 'scope' => 'branch'],
                    ['name' => 'cashier.shifts', 'label' => 'إدارة ورديات الكاشير', 'scope' => 'branch'],
                    ['name' => 'cashier.invoices', 'label' => 'عرض الفواتير', 'scope' => 'branch'],
                    ['name' => 'cashier.refunds', 'label' => 'الاسترجاع / المرتجعات', 'scope' => 'branch'],
                ],
            ],
            [
                'key' => 'purchases',
                'label' => 'المشتريات',
                'feature' => null,
                'permissions' => [
                    ['name' => 'purchases.view', 'label' => 'عرض المشتريات', 'scope' => 'central'],
                    ['name' => 'purchases.manage', 'label' => 'إدارة المشتريات', 'scope' => 'central'],
                ],
            ],
            [
                'key' => 'expenses',
                'label' => 'المصروفات',
                'feature' => null,
                'permissions' => [
                    ['name' => 'expenses.view', 'label' => 'عرض المصروفات', 'scope' => 'either'],
                    ['name' => 'expenses.manage', 'label' => 'إدارة المصروفات', 'scope' => 'either', 'description' => 'إضافة وتعديل وحذف المصروفات'],
                ],
            ],
            [
                'key' => 'sales',
                'label' => 'تقارير المبيعات',
                'feature' => null,
                'permissions' => [
                    ['name' => 'sales.reports', 'label' => 'عرض تقارير المبيعات', 'scope' => 'either'],
                ],
            ],
            [
                'key' => 'employees',
                'label' => 'الموظفون والحضور',
                'feature' => null,
                'permissions' => [
                    ['name' => 'employees.view', 'label' => 'عرض الموظفين', 'scope' => 'either', 'description' => 'الاطلاع على قائمة الموظفين (قراءة فقط)'],
                    ['name' => 'employees.manage', 'label' => 'إدارة الموظفين', 'scope' => 'either', 'description' => 'إضافة وتعديل بيانات الموظفين'],
                    ['name' => 'employees.delete', 'label' => 'حذف الموظفين', 'scope' => 'either'],
                    ['name' => 'employees.attendance', 'label' => 'تسجيل الحضور والانصراف', 'scope' => 'branch'],
                    ['name' => 'employees.attendance_settings', 'label' => 'إعدادات الحضور والجداول', 'scope' => 'branch'],
                    ['name' => 'employees.attendance_groups', 'label' => 'مجموعات الحضور', 'scope' => 'branch'],
                    ['name' => 'employees.salaries', 'label' => 'الرواتب والسحوبات', 'scope' => 'either'],
                    ['name' => 'employees.discounts', 'label' => 'إضافة خصومات', 'scope' => 'either', 'description' => 'إضافة خصم يدوي على الموظف'],
                    [
                        'name' => 'employees.manage_penalties',
                        'label' => 'إزالة الخصومات والإعفاء من الجزاءات',
                        'scope' => 'either',
                        'description' => 'إزالة خصم قائم، وإعفاء أو استعادة جزاء الغياب من صفحات الموظفين ومسحوبات الرواتب',
                    ],
                ],
            ],
            [
                'key' => 'users',
                'label' => 'المستخدمون والصلاحيات',
                'feature' => null,
                'permissions' => [
                    ['name' => 'users.view', 'label' => 'عرض المستخدمين', 'scope' => 'central'],
                    ['name' => 'users.manage', 'label' => 'إدارة المستخدمين', 'scope' => 'central'],
                    ['name' => 'roles.manage', 'label' => 'إدارة الفرق والصلاحيات', 'scope' => 'central'],
                ],
            ],
            [
                'key' => 'branches',
                'label' => 'الفروع',
                'feature' => null,
                'permissions' => [
                    ['name' => 'branches.view', 'label' => 'عرض الفروع', 'scope' => 'central'],
                    ['name' => 'branches.manage', 'label' => 'إدارة الفروع', 'scope' => 'central'],
                    ['name' => 'branches.switch', 'label' => 'التبديل بين الفروع', 'scope' => 'central'],
                ],
            ],
            [
                'key' => 'display',
                'label' => 'شاشة العرض',
                'feature' => null,
                'permissions' => [
                    ['name' => 'display.manage', 'label' => 'إدارة شاشة العرض', 'scope' => 'central'],
                ],
            ],
            [
                'key' => 'feedback',
                'label' => 'التقييمات',
                'feature' => null,
                'permissions' => [
                    ['name' => 'feedback.view', 'label' => 'عرض التقييمات', 'scope' => 'central'],
                    ['name' => 'feedback.manage', 'label' => 'إدارة التقييمات', 'scope' => 'central'],
                ],
            ],
            [
                'key' => 'job_applications',
                'label' => 'طلبات التوظيف',
                'feature' => null,
                'permissions' => [
                    ['name' => 'job_applications.view', 'label' => 'عرض طلبات التوظيف', 'scope' => 'central'],
                    ['name' => 'job_applications.manage', 'label' => 'إدارة طلبات التوظيف', 'scope' => 'central'],
                ],
            ],
            [
                'key' => 'barista',
                'label' => 'الريسبي / الباريستا',
                'feature' => null,
                'permissions' => [
                    ['name' => 'barista.use', 'label' => 'استخدام شاشة الريسبي', 'scope' => 'either'],
                ],
            ],
            [
                'key' => 'closing_photos',
                'label' => 'صور التقفيلة',
                'feature' => 'closing_photos',
                'permissions' => [
                    ['name' => 'closing_photos.browse', 'label' => 'استعراض صور التقفيلة', 'scope' => 'either'],
                    ['name' => 'closing_photos.submit', 'label' => 'رفع صور التقفيلة', 'scope' => 'branch'],
                    ['name' => 'closing_photos.settings', 'label' => 'إعدادات صور التقفيلة', 'scope' => 'central'],
                ],
            ],
            [
                'key' => 'tenant_settings',
                'label' => 'إعدادات البراند',
                'feature' => null,
                'permissions' => [
                    ['name' => 'tenant.settings', 'label' => 'إدارة إعدادات البراند', 'scope' => 'central'],
                ],
            ],
        ];
    }

    /**
     * صلاحيات Spatie القديمة → الصلاحيات الجديدة المكافئة (للترحيل الآمن).
     *
     * @return array<string, list<string>>
     */
    public static function legacyMap(): array
    {
        return [
            // ملاحظة: لا نوسّع manage products إلى products.manage لأن المسارات كانت
            // تمنع الكتابة عن غير السوبر أدمن رغم وجود الصلاحية القديمة.
            'view sales reports' => ['sales.reports'],
            'view product sales analysis' => ['products.sales_analysis'],
            'manage products' => ['products.view'],
            'use cashier' => ['cashier.use', 'cashier.shifts', 'cashier.invoices'],
            'manage users' => ['users.view', 'users.manage'],
            'manage employee attendance' => ['employees.view', 'employees.attendance'],
        ];
    }

    /**
     * الصلاحيات الافتراضية لكل دور أساسي — تطابق السلوك الفعلي الحالي في النظام.
     *
     * @return array<string, list<string>>
     */
    public static function systemRoleDefaults(): array
    {
        return [
            'super admin' => self::allPermissionNames(),
            'admin' => [
                'products.view',
                'products.cost_analysis',
                'inventory.branch_pull',
                'inventory.fridge_pull',
                'cashier.use',
                'cashier.shifts',
                'cashier.invoices',
                'cashier.refunds',
                'purchases.view',
                'purchases.manage',
                'expenses.view',
                'expenses.manage',
                'sales.reports',
                'employees.view',
                'employees.manage',
                'employees.attendance',
                'employees.attendance_settings',
                'employees.salaries',
                'employees.discounts',
                'feedback.view',
                'feedback.manage',
                'job_applications.view',
                'job_applications.manage',
                'barista.use',
                'closing_photos.browse',
                'closing_photos.submit',
            ],
            'cashier' => [
                'products.view',
                'inventory.branch_pull',
                'inventory.fridge_pull',
                'cashier.use',
                'cashier.shifts',
                'cashier.invoices',
                'cashier.refunds',
                'purchases.view',
                'purchases.manage',
                'expenses.view',
                'expenses.manage',
                'employees.view',
                'employees.attendance',
                'employees.salaries',
            ],
            'barista' => [
                'barista.use',
            ],
            'hr' => [
                'employees.view',
                'employees.discounts',
                'closing_photos.browse',
            ],
        ];
    }

    /** @return list<string> */
    public static function allPermissionNames(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group) => collect($group['permissions'])->pluck('name'))
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function legacyPermissionNames(): array
    {
        return array_keys(self::legacyMap());
    }

    /**
     * مجموعات للعرض بعد تصفية ميزات الـ tenant.
     *
     * @param  array<string, bool>  $features
     * @return list<array{key: string, label: string, feature: ?string, permissions: list<array>, count: int}>
     */
    public static function groupsForFeatures(array $features): array
    {
        return collect(self::groups())
            ->filter(function (array $group) use ($features) {
                $feature = $group['feature'] ?? null;
                if ($feature === null) {
                    return true;
                }

                return (bool) ($features[$feature] ?? false);
            })
            ->map(function (array $group) {
                return [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'feature' => $group['feature'] ?? null,
                    'permissions' => array_map(function (array $p) {
                        return [
                            'name' => $p['name'],
                            'label' => $p['label'],
                            'scope' => $p['scope'],
                            'scope_label' => match ($p['scope']) {
                                'branch' => 'فرع',
                                'central' => 'مركزي',
                                default => 'فرع / مركزي',
                            },
                            'description' => $p['description'] ?? null,
                        ];
                    }, $group['permissions']),
                    'count' => count($group['permissions']),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function permissionNamesForFeatures(array $features): array
    {
        return collect(self::groupsForFeatures($features))
            ->flatMap(fn (array $g) => collect($g['permissions'])->pluck('name'))
            ->values()
            ->all();
    }
}
