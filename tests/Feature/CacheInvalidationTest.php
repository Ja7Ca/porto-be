<?php

declare(strict_types=1);

use App\Models\MockProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use function Pest\Laravel\patchJson;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('it invalidates product detail cache on price update', function () {
    $product = MockProduct::factory()->create([
        'price' => 100.0,
    ]);

    $cacheKey = "product:detail:{$product->id}";

    // Set cache manual
    Cache::put($cacheKey, $product->toArray(), 3600);
    expect(Cache::has($cacheKey))->toBeTrue();

    // Jalankan update dengan harga baru
    $response = patchJson("/api/admin/products/{$product->id}/price", [
        'price' => 150.0,
    ]);

    $response->assertStatus(200);

    // Cek cache harusnya sudah terhapus
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('it does not invalidate cache if updated price is identical', function () {
    $product = MockProduct::factory()->create([
        'price' => 100.0,
    ]);

    $cacheKey = "product:detail:{$product->id}";

    // Set cache manual
    Cache::put($cacheKey, $product->toArray(), 3600);
    expect(Cache::has($cacheKey))->toBeTrue();

    // Jalankan update dengan harga yang sama persis
    $response = patchJson("/api/admin/products/{$product->id}/price", [
        'price' => 100.0,
    ]);

    $response->assertStatus(200);

    // Cek cache harus tetap utuh (Eloquent tidak menganggap dirty, event updated tidak terpicu)
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('it invalidates cache when product is deleted', function () {
    $product = MockProduct::factory()->create([
        'price' => 100.0,
    ]);

    $cacheKey = "product:detail:{$product->id}";

    // Set cache manual
    Cache::put($cacheKey, $product->toArray(), 3600);
    expect(Cache::has($cacheKey))->toBeTrue();

    // Hapus produk langsung via Eloquent
    $product->delete();

    // Cek cache harus terhapus
    expect(Cache::has($cacheKey))->toBeFalse();
});
