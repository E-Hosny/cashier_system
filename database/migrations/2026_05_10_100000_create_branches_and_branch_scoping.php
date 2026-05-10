<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        $branchScopedTables = ['orders', 'employees', 'attendance_groups', 'cashier_shifts'];
        foreach ($branchScopedTables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            if (! Schema::hasColumn($tbl, 'branch_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
            });
        }

        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'branch_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
            });
        }

        if (Schema::hasTable('invoice_sequences')) {
            if (Schema::hasColumn('invoice_sequences', 'date_code')) {
                try {
                    Schema::table('invoice_sequences', function (Blueprint $table) {
                        $table->dropUnique(['date_code']);
                    });
                } catch (\Throwable $e) {
                    //
                }
            }
            if (! Schema::hasColumn('invoice_sequences', 'branch_id')) {
                Schema::table('invoice_sequences', function (Blueprint $table) {
                    $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
                });
            }
        }

        $tenantIds = DB::table('tenants')->pluck('id');
        foreach ($tenantIds as $tenantId) {
            $branchId = DB::table('branches')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => 'الفرع الرئيسي',
                'slug' => 'main-'.$tenantId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($branchScopedTables as $tbl) {
                if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'branch_id')) {
                    DB::table($tbl)->where('tenant_id', $tenantId)->whereNull('branch_id')->update(['branch_id' => $branchId]);
                }
            }

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'branch_id')) {
                DB::table('users')->where('tenant_id', $tenantId)->whereNull('branch_id')->update(['branch_id' => $branchId]);
            }

            if (Schema::hasTable('invoice_sequences') && Schema::hasColumn('invoice_sequences', 'branch_id')) {
                DB::table('invoice_sequences')->where('tenant_id', $tenantId)->whereNull('branch_id')->update(['branch_id' => $branchId]);
            }
        }

        if (Schema::hasTable('invoice_sequences') && Schema::hasColumn('invoice_sequences', 'branch_id')) {
            DB::table('invoice_sequences')->whereNull('branch_id')->delete();

            $tenantCol = Schema::hasColumn('invoice_sequences', 'tenant_id');
            Schema::table('invoice_sequences', function (Blueprint $table) use ($tenantCol) {
                if ($tenantCol) {
                    $table->unique(['tenant_id', 'branch_id', 'date_code'], 'invoice_sequences_tenant_branch_date_unique');
                } else {
                    $table->unique(['branch_id', 'date_code'], 'invoice_sequences_branch_date_unique');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_sequences')) {
            Schema::table('invoice_sequences', function (Blueprint $table) {
                if (Schema::hasColumn('invoice_sequences', 'branch_id')) {
                    try {
                        $table->dropUnique('invoice_sequences_tenant_branch_date_unique');
                    } catch (\Throwable $e) {
                        try {
                            $table->dropUnique('invoice_sequences_branch_date_unique');
                        } catch (\Throwable $e2) {
                            //
                        }
                    }
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                }
            });
            Schema::table('invoice_sequences', function (Blueprint $table) {
                $table->unique('date_code');
            });
        }

        foreach (['cashier_shifts', 'attendance_groups', 'employees', 'orders'] as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'branch_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                });
            }
        }

        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'branch_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        Schema::dropIfExists('branches');
    }
};
