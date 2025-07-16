<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\OfflineOrder;
use App\Models\OfflineCache;
use App\Services\OfflineService;
use Tests\TestCase;

class OfflineSystemTest extends TestCase
{
    protected $user;
    protected $product;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        // تشغيل الهجرات الأساسية أولاً
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_03_20_081838_create_products_table.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_03_26_144805_create_orders_table.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_03_26_144843_create_order_items_table.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_05_30_125916_create_categories_table.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_01_20_000000_create_offline_orders_table.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_01_20_000001_create_offline_cache_table.php']);
        
        // إنشاء مستخدم للاختبار
        $this->user = User::factory()->create();
        
        // إنشاء فئة
        $this->category = Category::create([
            'name' => 'مشروبات',
            'tenant_id' => $this->user->tenant_id
        ]);
        
        // إنشاء منتج
        $this->product = Product::create([
            'name' => 'عصير برتقال',
            'type' => 'finished',
            'category_id' => $this->category->id,
            'tenant_id' => $this->user->tenant_id,
            'size_variants' => [
                ['size' => 'medium', 'price' => 15.00]
            ]
        ]);
    }

    /** @test */
    public function it_can_create_offline_order()
    {
        $this->actingAs($this->user);

        $orderData = [
            'total_price' => 30.00,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'quantity' => 2,
                    'price' => 15.00,
                    'size' => 'medium'
                ]
            ]
        ];

        $response = $this->postJson('/offline/orders', $orderData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseHas('offline_orders', [
            'user_id' => $this->user->id,
            'total' => 30.00,
            'payment_method' => 'cash',
            'status' => 'pending_sync'
        ]);
    }

    /** @test */
    public function it_can_sync_offline_orders()
    {
        $this->actingAs($this->user);

        // إنشاء طلب في وضع عدم الاتصال
        $offlineOrder = OfflineOrder::create([
            'offline_id' => 'OFF_20250120_123456_ABC123',
            'user_id' => $this->user->id,
            'total' => 30.00,
            'payment_method' => 'cash',
            'status' => 'pending_sync',
            'invoice_number' => 'INV001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'quantity' => 2,
                    'price' => 15.00,
                    'size' => 'medium'
                ]
            ],
            'stock_movements' => []
        ]);

        $response = $this->postJson('/offline/sync');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // التحقق من تحويل الطلب إلى طلب عادي
        $this->assertDatabaseHas('orders', [
            'total' => 30.00,
            'payment_method' => 'cash'
        ]);

        // التحقق من تحديث حالة الطلب
        $this->assertDatabaseHas('offline_orders', [
            'id' => $offlineOrder->id,
            'status' => 'synced'
        ]);
    }

    /** @test */
    public function it_can_cache_data_for_offline_use()
    {
        $this->actingAs($this->user);

        $cacheData = [
            'products' => [$this->product->toArray()],
            'categories' => [$this->category->toArray()]
        ];

        OfflineCache::set($this->user->id, 'test_data', $cacheData);

        $cachedData = OfflineCache::get($this->user->id, 'test_data');

        $this->assertEquals($cacheData, $cachedData);
    }

    /** @test */
    public function it_can_get_offline_stats()
    {
        $this->actingAs($this->user);

        // إنشاء بعض الطلبات للاختبار
        OfflineOrder::create([
            'offline_id' => 'OFF_001',
            'user_id' => $this->user->id,
            'total' => 30.00,
            'payment_method' => 'cash',
            'status' => 'pending_sync'
        ]);

        OfflineOrder::create([
            'offline_id' => 'OFF_002',
            'user_id' => $this->user->id,
            'total' => 50.00,
            'payment_method' => 'card',
            'status' => 'synced'
        ]);

        $response = $this->getJson('/offline/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'stats' => [
                    'total',
                    'pending',
                    'synced',
                    'failed',
                    'total_amount'
                ]
            ]);
    }

    /** @test */
    public function it_can_check_connection_status()
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/offline/check-connection');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'isOnline',
                'hasPendingOrders'
            ]);
    }

    /** @test */
    public function it_can_retry_failed_orders()
    {
        $this->actingAs($this->user);

        // إنشاء طلب فاشل
        OfflineOrder::create([
            'offline_id' => 'OFF_FAILED',
            'user_id' => $this->user->id,
            'total' => 30.00,
            'payment_method' => 'cash',
            'status' => 'failed',
            'sync_error' => 'Database connection failed'
        ]);

        $response = $this->postJson('/offline/retry');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // التحقق من تحديث حالة الطلب
        $this->assertDatabaseHas('offline_orders', [
            'offline_id' => 'OFF_FAILED',
            'status' => 'pending_sync'
        ]);
    }

    /** @test */
    public function it_can_cleanup_synced_orders()
    {
        $this->actingAs($this->user);

        // إنشاء طلب مزامن
        OfflineOrder::create([
            'offline_id' => 'OFF_SYNCED',
            'user_id' => $this->user->id,
            'total' => 30.00,
            'payment_method' => 'cash',
            'status' => 'synced'
        ]);

        $response = $this->postJson('/offline/cleanup');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // التحقق من حذف الطلب المزامن
        $this->assertDatabaseMissing('offline_orders', [
            'offline_id' => 'OFF_SYNCED'
        ]);
    }

    /** @test */
    public function it_can_export_offline_orders()
    {
        $this->actingAs($this->user);

        // إنشاء طلبات للتصدير
        OfflineOrder::create([
            'offline_id' => 'OFF_EXPORT1',
            'user_id' => $this->user->id,
            'total' => 30.00,
            'payment_method' => 'cash',
            'status' => 'pending_sync'
        ]);

        OfflineOrder::create([
            'offline_id' => 'OFF_EXPORT2',
            'user_id' => $this->user->id,
            'total' => 50.00,
            'payment_method' => 'card',
            'status' => 'synced'
        ]);

        $response = $this->getJson('/offline/export');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'orders',
                'export_date',
                'total_count',
                'total_amount'
            ]);
    }

    /** @test */
    public function it_handles_offline_mode_in_cashier()
    {
        $this->actingAs($this->user);

        $orderData = [
            'total_price' => 30.00,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'quantity' => 2,
                    'price' => 15.00,
                    'size' => 'medium'
                ]
            ]
        ];

        // محاكاة انقطاع الاتصال
        $this->mock(OfflineService::class, function ($mock) {
            $mock->shouldReceive('isOnline')->andReturn(false);
            $mock->shouldReceive('createOfflineOrder')->andReturn([
                'success' => true,
                'offline_id' => 'OFF_TEST',
                'invoice_number' => 'INV_TEST',
                'message' => 'تم إنشاء الطلب في وضع عدم الاتصال بنجاح!'
            ]);
        });

        $response = $this->postJson('/store-order', $orderData);

        $response->assertStatus(200)
            ->assertJson([
                'is_offline' => true
            ]);
    }
} 