<?php

use App\Models\MockProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('it can fetch product details and returns correct structure and source metadata', function () {
    $product = MockProduct::factory()->create();

    // First request: Should query database
    $response = $this->getJson("/api/products/{$product->id}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'price',
                'description',
                'sku',
                'created_at',
                'updated_at',
            ],
            'meta' => [
                'execution_time_ms',
                'source',
            ],
        ])
        ->assertJsonPath('meta.source', 'Database');

    // Second request: Should be served from Redis (Cache Hit)
    $secondResponse = $this->getJson("/api/products/{$product->id}");

    $secondResponse->assertSuccessful()
        ->assertJsonPath('meta.source', 'Redis (Cache Hit)');
});

test('it returns 404 if product does not exist', function () {
    $response = $this->getJson('/api/products/999999');

    $response->assertNotFound()
        ->assertJsonStructure([
            'message',
            'meta' => [
                'execution_time_ms',
                'source',
            ],
        ])
        ->assertJsonPath('meta.source', 'Database (Not Found)');
});
