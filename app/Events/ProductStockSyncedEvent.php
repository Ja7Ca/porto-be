<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductStockSyncedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $productId;
    public int $newStock;

    /**
     * Create a new event instance.
     */
    public function __construct(int $productId, int $newStock)
    {
        $this->productId = $productId;
        $this->newStock = $newStock;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('inventory-tracker.' . $this->productId);
    }

    /**
     * Get the event name that should be broadcast.
     */
    public function broadcastAs(): string
    {
        return 'StockUpdated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'new_stock' => $this->newStock,
        ];
    }
}
