<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MockProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Handle the MockProduct "updated" event.
     */
    public function updated(MockProduct $mockProduct): void
    {
        $hasPriceOrNameChanged = $mockProduct->wasChanged(['price', 'name']);
        $hasStockChanged = $mockProduct->wasChanged('stok');

        if ($hasPriceOrNameChanged || $hasStockChanged) {
            Cache::forget("product:detail:{$mockProduct->id}");
            Log::info("Cache untuk produk {$mockProduct->id} dihancurkan karena ada perubahan data (nama/harga/stok).");
        }

        if ($hasStockChanged) {
            broadcast(new \App\Events\ProductStockSyncedEvent($mockProduct->id, (int) $mockProduct->stok))->toOthers();
            Log::info("Siaran real-time dikirim untuk produk {$mockProduct->id} dengan stok baru: {$mockProduct->stok}");
        }
    }

    /**
     * Handle the MockProduct "deleted" event.
     */
    public function deleted(MockProduct $mockProduct): void
    {
        Cache::forget("product:detail:{$mockProduct->id}");
        Log::info("Cache untuk produk {$mockProduct->id} dihancurkan karena produk dihapus dari database.");
    }
}
