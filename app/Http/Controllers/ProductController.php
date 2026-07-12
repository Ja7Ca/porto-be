<?php

namespace App\Http\Controllers;

use App\Models\MockProduct;
use App\Services\AdvancedCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected AdvancedCacheService $cacheService;

    public function __construct(AdvancedCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * List products for the List Virtualization + Infinite Scroll lab.
     *
     * Uses keyset (cursor) pagination — `WHERE id > cursor ORDER BY id
     * LIMIT n` — instead of OFFSET pagination. With 100k seeded rows,
     * OFFSET pagination gets linearly slower the deeper a page is (the
     * DB still has to walk past every skipped row), and its result set
     * can shift under concurrent inserts. Keyset seeks directly via the
     * primary key index, so cost stays ~constant regardless of depth.
     * The trade-off — no jumping to an arbitrary page number — doesn't
     * cost anything here, since infinite scroll only ever asks for "the
     * next chunk," never a random page.
     *
     * Deliberately uncached: the Redis cache-aside pattern is already
     * demonstrated by show() above; this endpoint's job is to prove the
     * FE can handle a large, fast-scrolling dataset efficiently, not to
     * re-prove caching.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => 'sometimes|nullable|integer|min:0',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $cursor = (int) $request->query('cursor', 0);
        $limit = (int) $request->query('limit', 30);

        $query = MockProduct::query()->orderBy('id');

        if ($cursor > 0) {
            $query->where('id', '>', $cursor);
        }

        $products = $query->limit($limit)->get(['id', 'name', 'price', 'stok', 'description']);

        $nextCursor = $products->isNotEmpty() ? (int) $products->last()->id : null;
        $hasMore = $products->count() === $limit;

        return response()->json([
            'data' => $products,
            'meta' => [
                'next_cursor' => $hasMore ? $nextCursor : null,
                'has_more' => $hasMore,
                'count' => $products->count(),
            ],
        ], 200);
    }

    public function show(int $id): JsonResponse
    {
        $startTime = microtime(true);
        $isDbQueryExecuted = false;

        $cacheKey = "product:detail:{$id}";
        $oneHour = 3600;

        $product = $this->cacheService->getOrSet($cacheKey, $oneHour, function () use ($id, &$isDbQueryExecuted) {
            $isDbQueryExecuted = true;
            // Silakan cek Laravel Log atau Terminal, string ini tidak akan nge-spam saat trafik tinggi
            Log::info("Membaca langsung dari database untuk ID: {$id}");

            $product = MockProduct::find($id);

            return $product ? $product->toArray() : null;
        });

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
                'meta' => [
                    'execution_time_ms' => $executionTimeMs,
                    'source' => 'Database (Not Found)',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $product,
            'meta' => [
                'execution_time_ms' => $executionTimeMs,
                'source' => $isDbQueryExecuted ? 'Database' : 'Redis (Cache Hit)',
            ],
        ], 200);
    }

    public function updatePrice(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $product = MockProduct::findOrFail($id);
        $product->price = (float) $request->input('price');
        $product->save();

        return response()->json([
            'message' => 'Product price updated successfully',
            'data' => $product,
        ], 200);
    }

    /**
     * Deliberately flaky endpoint for the Retry + Exponential Backoff lab.
     *
     * Stateless by design: no counter is stored server-side, avoiding the
     * shared-mutable-state race a server-side counter would introduce
     * under concurrent demo sessions — exactly the class of bug the other
     * panels in this lab exist to demonstrate fixing.
     *
     * Attempt #1 always fails (503) so the retry/backoff behavior is
     * guaranteed to be visible at least once. From attempt #2 onward,
     * success is an independent 50% coin flip per request — so identical
     * `attempt` values can return different results across calls, and
     * there's a genuine (~12.5%) chance of exhausting all attempts and
     * reaching the "give up" state, instead of a fixed threshold that
     * would make failure mathematically unreachable within the attempt
     * budget.
     */
    public function flaky(Request $request, int $id): JsonResponse
    {
        $attempt = max(1, (int) $request->query('attempt', 1));

        $succeeds = $attempt > 1 && mt_rand(1, 100) <= 50;

        if (! $succeeds) {
            return response()->json([
                'message' => 'Service Temporarily Unavailable',
                'meta' => [
                    'attempt' => $attempt,
                ],
            ], 503);
        }

        $product = MockProduct::find($id);

        return response()->json([
            'message' => 'Request succeeded',
            'data' => $product,
            'meta' => [
                'attempt' => $attempt,
            ],
        ], 200);
    }

    /**
     * Reset a product's stock to a clean demo value.
     *
     * Seeded stock is random per product (10-100) and has no recorded
     * "original" value to restore, so this resets to a fixed, reasonable
     * demo value instead (default 50). Saving through Eloquent triggers
     * ProductObserver::updated(), which already handles cache invalidation
     * and the realtime stock-sync broadcast — no extra wiring needed here.
     */
    public function resetStock(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'stok' => 'sometimes|integer|min:1|max:100000',
        ]);

        $product = MockProduct::findOrFail($id);
        $product->stok = (int) $request->input('stok', 50);
        $product->save();

        return response()->json([
            'message' => 'Product stock reset successfully',
            'data' => $product,
        ], 200);
    }
}
