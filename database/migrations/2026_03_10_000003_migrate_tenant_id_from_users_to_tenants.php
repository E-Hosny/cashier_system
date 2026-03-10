<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // 1) إضافة عمود مؤقت لربط المستخدم-المالك بالـ tenant
        if (!Schema::hasColumn('tenants', 'owner_user_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->unsignedBigInteger('owner_user_id')->nullable()->after('slug');
            });
        }

        // 2) إنشاء tenant افتراضي (إن لم يكن موجوداً) + tenant لكل مستخدم مالك (tenant_id = id)
        $now = now();
        $defaultTenantId = (int) DB::table('tenants')->where('slug', 'default')->value('id');
        if ($defaultTenantId === 0) {
            DB::table('tenants')->insert([
                'name' => 'Default',
                'slug' => 'default',
                'owner_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $defaultTenantId = (int) DB::table('tenants')->where('slug', 'default')->value('id');
        }

        if (Schema::hasColumn('users', 'tenant_id')) {
            $slugExpr = $driver === 'sqlite'
                ? "('user-' || u.id)"
                : "CONCAT('user-', u.id)";
            DB::statement("
                INSERT INTO tenants (name, slug, owner_user_id, created_at, updated_at)
                SELECT u.name, {$slugExpr}, u.id, ?, ?
                FROM users u
                WHERE u.tenant_id = u.id
            ", [$now, $now]);
        }

        $tablesWithUserTenantId = [
            'users',
            'products',
            'orders',
            'order_items',
            'purchases',
            'suppliers',
            'employee_discounts',
        ];

        foreach ($tablesWithUserTenantId as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            // إضافة عمود tenant_id_new يشير إلى tenants
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id_new')->nullable()->after('tenant_id');
            });

            // تعبئة القيم من جدول tenants حسب owner_user_id
            if ($tableName === 'users') {
                DB::statement("
                    UPDATE users u
                    SET u.tenant_id_new = COALESCE(
                        (SELECT id FROM tenants t WHERE t.owner_user_id = u.tenant_id LIMIT 1),
                        ?
                    )
                ", [$defaultTenantId]);
            } else {
                DB::statement("
                    UPDATE {$tableName} t
                    SET t.tenant_id_new = COALESCE(
                        (SELECT id FROM tenants tn WHERE tn.owner_user_id = t.tenant_id LIMIT 1),
                        ?
                    )
                ", [$defaultTenantId]);
            }

            // إسقاط المفتاح الأجنبي والعمود القديم
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });

            // إعادة تسمية tenant_id_new -> tenant_id
            if ($driver === 'sqlite') {
                DB::statement("ALTER TABLE {$tableName} RENAME COLUMN tenant_id_new TO tenant_id");
            } else {
                DB::statement("ALTER TABLE {$tableName} CHANGE tenant_id_new tenant_id BIGINT UNSIGNED NULL");
            }

            // إضافة المفتاح الأجنبي إلى tenants
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }

        // إزالة العمود المؤقت من tenants
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('owner_user_id');
        });
    }

    public function down(): void
    {
        // التراجع معقد (استعادة FK إلى users)؛ يُفضّل عدم استخدام down أو تنفيذه يدوياً
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'owner_user_id')) {
                $table->unsignedBigInteger('owner_user_id')->nullable()->after('slug');
            }
        });

        $tablesWithTenantId = [
            'users', 'products', 'orders', 'order_items', 'purchases', 'suppliers', 'employee_discounts',
        ];

        foreach ($tablesWithTenantId as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id_old')->nullable()->after('tenant_id');
            });
            DB::statement("
                UPDATE {$tableName} t
                SET t.tenant_id_old = (SELECT owner_user_id FROM tenants tn WHERE tn.id = t.tenant_id LIMIT 1)
            ");
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::statement("ALTER TABLE {$tableName} RENAME COLUMN tenant_id_old TO tenant_id");
            } else {
                DB::statement("ALTER TABLE {$tableName} CHANGE tenant_id_old tenant_id BIGINT UNSIGNED NULL");
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        DB::table('tenants')->truncate();
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('owner_user_id');
        });
    }

};
