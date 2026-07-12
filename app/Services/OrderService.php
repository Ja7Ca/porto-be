<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendOrderEmailJob;
use App\Models\MockProduct;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Create/mutate order and simulate database processing delay.
     *
     * This is a MOCK implementation — no real DB write, fake order id and
     * price — used by the Idempotency Guard lab, which only cares about
     * whether the SAME request gets processed twice, not about real stock
     * or pricing. See createSecureOrder() below for the real one.
     *
     * @param  array  $data
     * @return array
     */
    public function createOrder(array $data): array
    {
        // Simulasikan database query / proses mutasi berat
        usleep(1500000);

        return [
            'order_id' => rand(100000, 999999),
            'product_id' => (int) ($data['product_id'] ?? 0),
            'quantity' => (int) ($data['quantity'] ?? 0),
            'status' => 'PAID',
            'total_price' => rand(10000, 1000000),
        ];
    }

    /**
     * Create an order using pessimistic locking (SELECT ... FOR UPDATE)
     * so concurrent checkouts on the same product serialize instead of
     * racing on stock. Used by POST /orders/secure — the High Concurrency
     * and Optimistic UI labs both hit this same real endpoint. Unlike
     * createOrder() above, this genuinely writes to the database and
     * genuinely decrements stock.
     *
     * @param  int  $productId
     * @param  int  $quantity
     * @return array{order: \App\Models\Order, remaining_stock: int}
     *
     * @throws \Illuminate\Validation\ValidationException if the product
     *         doesn't exist, or there isn't enough stock left.
     */
    public function createSecureOrder(int $productId, int $quantity): array
    {
        return DB::transaction(function () use ($productId, $quantity) {
            // Ambil data produk menggunakan Pessimistic Lock
            $product = MockProduct::where('id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'product_id' => ['Produk tidak ditemukan.'],
                ]);
            }

            // Lakukan pengecekan kondisi stok
            if ($product->stok < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Maaf, stok produk telah habis!'],
                ]);
            }

            // Kurangi stok produk
            $product->decrement('stok', $quantity);

            // Buat data order baru di database
            $order = Order::create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'total_price' => $product->price * $quantity,
                'status' => 'PAID',
            ]);

            // Pemicu (dispatch) Background Job
            SendOrderEmailJob::dispatch($order->id);

            return [
                'order' => $order,
                'remaining_stock' => $product->stok,
            ];
        });
    }
}
