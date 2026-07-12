<?php

declare(strict_types=1);

use App\Jobs\SendOrderEmailJob;
use App\Models\MockProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

test('it successfuly orders and decrements stock and dispatches background job', function () {
    Queue::fake();
    \Illuminate\Support\Facades\Event::fake([
        \App\Events\ProductStockSyncedEvent::class,
    ]);
    \Illuminate\Support\Facades\Cache::flush();

    $product = MockProduct::factory()->create([
        'stok' => 10,
    ]);

    $cacheKey = "product:detail:{$product->id}";
    \Illuminate\Support\Facades\Cache::put($cacheKey, $product->toArray(), 3600);
    expect(\Illuminate\Support\Facades\Cache::has($cacheKey))->toBeTrue();

    $response = postJson('/api/orders/secure', [
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.remaining_stock', 7)
        ->assertJsonPath('message', 'Order sukses diproses di latar belakang!');

    // Cek database
    $product->refresh();
    expect($product->stok)->toBe(7);

    // Cek bahwa cache produk sudah ter-invalidasi karena stok berkurang
    expect(\Illuminate\Support\Facades\Cache::has($cacheKey))->toBeFalse();

    // Cek bahwa siaran WebSocket untuk sinkronisasi stok dikirim
    \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\ProductStockSyncedEvent::class, function ($event) use ($product) {
        return $event->productId === $product->id && $event->newStock === 7;
    });

    // Cek background job ter-dispatch
    Queue::assertPushed(SendOrderEmailJob::class, function ($job) {
        return $job->orderId !== null;
    });
});

test('it rejects checkout when stock is insufficient', function () {
    Queue::fake();

    $product = MockProduct::factory()->create([
        'stok' => 2,
    ]);

    $response = postJson('/api/orders/secure', [
        'product_id' => $product->id,
        'quantity' => 5,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Maaf, stok produk telah habis!');

    // Cek database
    $product->refresh();
    expect($product->stok)->toBe(2);

    // Cek background job tidak ter-dispatch
    Queue::assertNotPushed(SendOrderEmailJob::class);
});
