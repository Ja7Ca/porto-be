<?php

declare(strict_types=1);

use App\Events\NewChatMessageEvent;
use App\Events\ProductStockSyncedEvent;
use App\Models\Chat;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

test('it can fetch latest 50 messages in order', function () {
    for ($i = 1; $i <= 55; $i++) {
        Chat::create([
            'sender' => 'buyer',
            'message' => "Message #{$i}",
        ]);
    }

    $response = getJson('/api/playground/messages');

    $response->assertStatus(200)
        ->assertJsonCount(50);

    // Make sure it returns the last 50 (i.e. message #6 to #55)
    $data = $response->json();
    expect($data[0]['message'])->toBe('Message #6');
    expect(end($data)['message'])->toBe('Message #55');
});

test('it sends message and broadcasts event to others', function () {
    Event::fake();

    $response = postJson('/api/playground/messages', [
        'sender' => 'admin',
        'message' => 'Welcome to active support!',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['status', 'data' => ['id', 'sender', 'message', 'created_at', 'updated_at']]);

    $this->assertDatabaseHas('chats', [
        'sender' => 'admin',
        'message' => 'Welcome to active support!',
    ]);

    Event::assertDispatched(NewChatMessageEvent::class);
});

test('it triggers manual sync and broadcasts stock event to others', function () {
    Event::fake();

    $response = postJson('/api/playground/sync-stock', [
        'product_id' => 999,
        'stock' => 123,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Stock sync triggered!');

    Event::assertDispatched(ProductStockSyncedEvent::class, function ($event) {
        return $event->productId === 999 && $event->newStock === 123;
    });
});
