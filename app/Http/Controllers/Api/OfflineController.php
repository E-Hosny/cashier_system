<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfflineController extends Controller
{
    /**
     * Health check endpoint
     */
    public function healthCheck()
    {
        return response()->json(['status' => 'online']);
    }

    /**
     * Store offline order
     */
    public function storeOfflineOrder(Request $request)
    {
        try {
            $data = $request->validate([
                'total_price' => 'required|numeric',
                'payment_method' => 'required|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric',
                'items.*.product_name' => 'required|string',
                'items.*.size' => 'nullable|string',
                'offline_id' => 'nullable|string', // Unique ID for offline orders
            ]);

            $order = null;
            
            DB::transaction(function () use ($data, &$order) {
                // Create order
                $order = Order::create([
                    'total' => $data['total_price'],
                    'payment_method' => $data['payment_method'],
                    'status' => 'completed',
                    'offline_id' => $data['offline_id'] ?? null,
                ]);

                // Create order items
                $orderItems = [];
                foreach ($data['items'] as $item) {
                    $orderItems[] = [
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'size' => $item['size'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                OrderItem::insert($orderItems);

                // Update stock (same logic as CashierController)
                $this->updateStock($data['items'], $order->id);
            });

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ الطلب بنجاح',
                'order_id' => $order->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Offline order storage failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'فشل في حفظ الطلب: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync offline orders
     */
    public function syncOfflineOrders(Request $request)
    {
        try {
            $offlineOrders = $request->input('orders', []);
            $syncedCount = 0;
            $failedCount = 0;

            foreach ($offlineOrders as $offlineOrder) {
                try {
                    $order = Order::create([
                        'total' => $offlineOrder['total_price'],
                        'payment_method' => $offlineOrder['payment_method'],
                        'status' => 'completed',
                        'offline_id' => $offlineOrder['offline_id'] ?? null,
                    ]);

                    // Create order items
                    $orderItems = [];
                    foreach ($offlineOrder['items'] as $item) {
                        $orderItems[] = [
                            'order_id' => $order->id,
                            'product_id' => $item['product_id'],
                            'product_name' => $item['product_name'],
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                            'size' => $item['size'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    OrderItem::insert($orderItems);

                    // Update stock
                    $this->updateStock($offlineOrder['items'], $order->id);

                    $syncedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to sync offline order: ' . $e->getMessage());
                    $failedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'synced_orders' => $syncedCount,
                'failed_orders' => $failedCount,
                'message' => "تم مزامنة {$syncedCount} طلب بنجاح"
            ]);

        } catch (\Exception $e) {
            Log::error('Offline sync failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'فشل في المزامنة: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get offline orders count
     */
    public function getOfflineOrdersCount()
    {
        $count = Order::whereNotNull('offline_id')->count();
        
        return response()->json([
            'count' => $count
        ]);
    }

    /**
     * Update stock for offline orders
     */
    private function updateStock($items, $orderId)
    {
        $stockUpdates = [];
        $stockMovements = [];
        
        // Get products
        $productIds = collect($items)->pluck('product_id')->unique();
        $products = \App\Models\Product::select('id', 'type', 'stock')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
        
        // Get ingredients for finished products
        $finishedProductIds = $products->where('type', 'finished')->keys();
        $ingredients = collect();
        if ($finishedProductIds->isNotEmpty()) {
            $ingredients = DB::table('ingredients')
                ->select('finished_product_id', 'raw_material_id', 'quantity_consumed', 'size')
                ->whereIn('finished_product_id', $finishedProductIds)
                ->get()
                ->groupBy('finished_product_id');
        }
        
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (!$product) continue;

            if ($product->type === 'finished') {
                $productIngredients = $ingredients->get($product->id, collect());
                $ingredientsForSize = $productIngredients->where('size', $item['size']);

                foreach ($ingredientsForSize as $ingredient) {
                    $quantityToDeduct = $item['quantity'] * $ingredient->quantity_consumed;
                    
                    if (!isset($stockUpdates[$ingredient->raw_material_id])) {
                        $stockUpdates[$ingredient->raw_material_id] = 0;
                    }
                    $stockUpdates[$ingredient->raw_material_id] -= $quantityToDeduct;
                    
                    $stockMovements[] = [
                        'product_id' => $ingredient->raw_material_id,
                        'quantity' => -$quantityToDeduct,
                        'type' => 'sale_deduction',
                        'related_order_id' => $orderId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            } else if ($product->type === 'raw' && $product->stock !== null) {
                if (!isset($stockUpdates[$product->id])) {
                    $stockUpdates[$product->id] = 0;
                }
                $stockUpdates[$product->id] -= $item['quantity'];
                
                $stockMovements[] = [
                    'product_id' => $product->id,
                    'quantity' => -$item['quantity'],
                    'type' => 'sale_deduction',
                    'related_order_id' => $orderId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        
        // Update stock
        foreach ($stockUpdates as $productId => $change) {
            DB::table('products')->where('id', $productId)->increment('stock', $change);
        }
        
        // Insert stock movements
        if (!empty($stockMovements)) {
            \App\Models\StockMovement::insert($stockMovements);
        }
    }
} 