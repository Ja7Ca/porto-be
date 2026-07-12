<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    protected OrderService $orderService;

    /**
     * Create a new controller instance.
     */
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Store a newly created order.
     *
     * @param  \App\Http\Requests\StoreOrderRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        // Validasi otomatis didelegasikan ke StoreOrderRequest
        $orderData = $this->orderService->createOrder($request->validated());

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'data' => $orderData,
            'meta' => [
                'execution_time_ms' => $executionTimeMs,
                'idempotency_status' => 'PROCESSED_NEW_TRANSACTION',
            ],
        ], 201);
    }

    /**
     * Store a newly created order with Pessimistic Locking protection.
     *
     * Transaction, locking, stock check, and order creation all live in
     * OrderService::createSecureOrder() — this method only validates the
     * request and translates the service's result/exceptions into an
     * HTTP response, which is the one thing a controller should be
     * doing.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeSecure(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $result = $this->orderService->createSecureOrder(
                (int) $request->input('product_id'),
                (int) $request->input('quantity')
            );

            return response()->json([
                'message' => 'Order sukses diproses di latar belakang!',
                'data' => [
                    'order_id' => $result['order']->id,
                    'product_id' => $result['order']->product_id,
                    'quantity' => $result['order']->quantity,
                    'status' => $result['order']->status,
                    'total_price' => $result['order']->total_price,
                    'remaining_stock' => $result['remaining_stock'],
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Maaf, stok produk telah habis!',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Secure checkout error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan sistem, silakan coba lagi.',
            ], 500);
        }
    }
}
