# Safe Inventory System — Backend

API dan server WebSocket (Laravel Reverb) yang menjadi backend dari serangkaian **lab interaktif** untuk mendemonstrasikan pola-pola backend engineering di dunia nyata: caching, concurrency, idempotency, dan real-time sync. Frontend-nya ada di folder `safe_inventory_system-fe` di sebelah folder ini, dan mengonsumsi API ini secara langsung.

## Tech Stack

- PHP 8.3+ / Laravel 13
- PostgreSQL — database utama
- Redis — cache, atomic lock (mutex), dan queue driver
- Laravel Reverb — WebSocket server (broadcasting real-time)
- Pest — testing framework

## Prasyarat

Sebelum instalasi, pastikan sudah terpasang dan **berjalan**:

- PHP 8.3 atau lebih baru + Composer
- PostgreSQL
- Redis server

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Lalu edit `.env`:

1. Set kredensial database PostgreSQL (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
2. Ubah tiga baris berikut — nilai default di `.env.example` (`database`/`log`) tidak akan menyalakan fitur cache/queue/broadcast yang didemonstrasikan lab-lab ini:
   ```
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   BROADCAST_CONNECTION=reverb
   ```
3. Generate kredensial Reverb (WebSocket):
   ```bash
   php artisan reverb:install
   ```
   Ini akan mengisi otomatis `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT` di `.env`. Nilai `REVERB_APP_KEY` inilah yang harus dicocokkan dengan `NEXT_PUBLIC_REVERB_KEY` di `.env.local` frontend.

Lalu jalankan migrasi dan seeder:

```bash
php artisan migrate
php artisan db:seed
```

**Catatan:** seeder membuat **100.000 baris data produk palsu** (dipakai lab List Virtualization di frontend) — proses ini bisa memakan waktu beberapa menit.

## Menjalankan

Proyek ini butuh **3 proses berjalan bersamaan**, plus PostgreSQL dan Redis yang sudah aktif:

```bash
# Terminal 1 — API server (http://127.0.0.1:8000)
php artisan serve

# Terminal 2 — WebSocket server (untuk lab /realtime)
php artisan reverb:start

# Terminal 3 — Queue worker (untuk job kirim email order di background)
php artisan queue:work
```

Kalau kamu cuma mau coba lab-lab yang tidak butuh WebSocket atau background job, `php artisan serve` saja sudah cukup — tapi lab **WebSocket Live Tracker** dan pengiriman email order tidak akan berfungsi tanpa proses 2 dan 3.

## API Routes

Semua route ada di `routes/api.php`, prefix `/api`:

| Method | Endpoint | Controller | Fungsi |
|---|---|---|---|
| GET | `/products` | `ProductController@index` | List produk dengan **keyset (cursor) pagination** — dipakai lab List Virtualization & Infinite Scroll |
| GET | `/products/{id}` | `ProductController@show` | Detail 1 produk, lewat `AdvancedCacheService` (Redis cache-aside + anti-stampede + anti-penetration) |
| GET | `/products/{id}/flaky` | `ProductController@flaky` | Endpoint yang sengaja gagal (HTTP 503) secara acak — dipakai lab Retry with Exponential Backoff |
| PATCH | `/admin/products/{id}/price` | `ProductController@updatePrice` | Update harga produk, memicu `ProductObserver` yang menghapus cache Redis-nya — dipakai lab Event-Driven Cache Invalidation |
| PATCH | `/admin/products/{id}/reset-stock` | `ProductController@resetStock` | Reset stok produk (dipakai untuk reset state lab Optimistic UI / Concurrency setelah diuji berkali-kali) |
| POST | `/orders` | `OrderController@store` | Buat order baru, dilindungi `IdempotencyMiddleware` lewat header `X-Idempotency-Key` — dipakai lab Idempotency Guard |
| POST | `/orders/secure` | `OrderController@storeSecure` | Buat order dengan **Pessimistic Locking** (`lockForUpdate()`) untuk mencegah race condition rebutan stok — dipakai lab High Concurrency |
| GET | `/playground/messages` | `RealTimePlaygroundController@getMessages` | Ambil riwayat pesan chat — lab WebSocket |
| POST | `/playground/messages` | `RealTimePlaygroundController@sendMessage` | Kirim pesan chat, broadcast lewat `NewChatMessageEvent` |
| POST | `/playground/sync-stock` | `RealTimePlaygroundController@triggerManualSync` | Trigger sinkronisasi stok manual, broadcast lewat `ProductStockSyncedEvent` |

## Struktur Project (yang relevan)

```
app/
├── Http/
│   ├── Controllers/       # ProductController, OrderController, RealTimePlaygroundController
│   └── Middleware/        # IdempotencyMiddleware — guard anti double-submit
├── Services/
│   ├── AdvancedCacheService.php   # Cache-aside + mutex lock (anti stampede) + null caching (anti penetration)
│   └── OrderService.php
├── Observers/
│   └── ProductObserver.php        # Auto-invalidate cache Redis saat data produk berubah
├── Events/                        # NewChatMessageEvent, ProductStockSyncedEvent — di-broadcast lewat Reverb
├── Jobs/
│   └── SendOrderEmailJob.php      # Background job, jalan lewat queue:work
└── Models/                        # MockProduct, Order, Chat, User

database/
├── migrations/
└── seeders/DatabaseSeeder.php     # Seed 100.000 MockProduct
```

## Testing

Test suite pakai Pest, sudah ada beberapa test feature untuk pola-pola di atas:

```bash
php artisan test
# atau
vendor/bin/pest
```

File test yang tersedia: `CacheInvalidationTest`, `IdempotencyTest`, `PessimisticLockingTest`, `ProductControllerTest`, `RealTimeBroadcastingTest`.
