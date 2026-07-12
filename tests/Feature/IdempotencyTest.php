<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Cache::flush();
});

test('it passes request normally without idempotency key', function () {
    $response = postJson('/api/orders', [
        'product_id' => 1,
        'quantity' => 2,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('meta.idempotency_status', 'PROCESSED_NEW_TRANSACTION')
        ->assertHeaderMissing('X-Cache-Lookup');
});

test('it processes new transaction with unique idempotency key', function () {
    $response = postJson('/api/orders', [
        'product_id' => 1,
        'quantity' => 2,
    ], [
        'X-Idempotency-Key' => 'unique-key-123',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('meta.idempotency_status', 'PROCESSED_NEW_TRANSACTION')
        ->assertHeaderMissing('X-Cache-Lookup');

    // Cek bahwa data tersimpan di cache
    $cached = Cache::get('idempotency:unique-key-123');
    expect($cached)->toBeArray()
        ->toHaveKey('status', 201)
        ->toHaveKey('body');
});

test('it blocks duplicate request when transaction is processing', function () {
    // Set status ke 'PROCESSING' secara manual untuk menyimulasikan request sedang berjalan
    Cache::put('idempotency:processing-key', 'PROCESSING', 300);

    $response = postJson('/api/orders', [
        'product_id' => 1,
        'quantity' => 2,
    ], [
        'X-Idempotency-Key' => 'processing-key',
    ]);

    $response->assertStatus(409)
        ->assertJsonFragment([
            'message' => 'Transaksi Anda sedang diproses. Mohon jangan klik dua kali.',
        ])
        ->assertJsonPath('meta.idempotency_status', 'BLOCKED_BY_LOCK');
});

test('it returns cached response for duplicate finished transaction', function () {
    // Jalankan request pertama
    $firstResponse = postJson('/api/orders', [
        'product_id' => 1,
        'quantity' => 2,
    ], [
        'X-Idempotency-Key' => 'finished-key',
    ]);

    $firstResponse->assertStatus(201);
    $firstData = $firstResponse->json('data');

    // Jalankan request kedua dengan key yang sama
    $secondResponse = postJson('/api/orders', [
        'product_id' => 1,
        'quantity' => 2,
    ], [
        'X-Idempotency-Key' => 'finished-key',
    ]);

    // Harus mengembalikan data yang sama persis (karena dicache) dan header X-Cache-Lookup => HIT_IDEMPOTENT
    $secondResponse->assertStatus(201)
        ->assertHeader('X-Cache-Lookup', 'HIT_IDEMPOTENT');
    
    expect($secondResponse->json('data.order_id'))->toBe($firstData['order_id']);
});

test('it clears idempotency key on failure', function () {
    // Request gagal karena input validation error (product_id missing)
    $response = postJson('/api/orders', [
        'quantity' => 2,
    ], [
        'X-Idempotency-Key' => 'failed-key',
    ]);

    $response->assertStatus(422);

    // Kunci idempotensi harusnya langsung dihapus karena gagal
    expect(Cache::has('idempotency:failed-key'))->toBeFalse();
});
