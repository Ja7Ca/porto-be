<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\NewChatMessageEvent;
use App\Events\ProductStockSyncedEvent;
use App\Models\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealTimePlaygroundController extends Controller
{
    /**
     * Get the last 50 chat messages.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMessages(): JsonResponse
    {
        $messages = Chat::latest('id')
            ->take(50)
            ->get()
            ->reverse()
            ->values();

        return response()->json($messages, 200);
    }

    /**
     * Send a new chat message and broadcast it.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'sender' => ['required', 'string', 'in:buyer,admin'],
            'message' => ['required', 'string'],
        ]);

        $chat = Chat::create([
            'sender' => $request->input('sender'),
            'message' => $request->input('message'),
        ]);

        // Broadcast to other users via WebSocket
        broadcast(new NewChatMessageEvent($chat))->toOthers();

        return response()->json([
            'status' => 'success',
            'data' => $chat,
        ], 201);
    }

    /**
     * Trigger manual stock synchronization over WebSocket.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function triggerManualSync(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer'],
            'stock' => ['required', 'integer'],
        ]);

        $productId = (int) $request->input('product_id');
        $stock = (int) $request->input('stock');

        $product = \App\Models\MockProduct::find($productId);
        if ($product) {
            $product->stok = $stock;
            $product->save();
        } else {
            $product = new \App\Models\MockProduct();
            $product->id = $productId;
            $product->name = 'Mock Product #' . $productId;
            $product->price = 10000;
            $product->stok = $stock;
            $product->sku = 'MOCK-' . $productId;
            $product->save();
            
            broadcast(new ProductStockSyncedEvent($productId, $stock))->toOthers();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Stock sync triggered!',
        ], 200);
    }
}
