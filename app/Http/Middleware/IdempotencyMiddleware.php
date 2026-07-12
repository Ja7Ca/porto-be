<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Idempotency-Key');

        // Jika tidak ada header X-Idempotency-Key, loloskan request biasa
        if (empty($key)) {
            return $next($request);
        }

        $cacheKey = "idempotency:{$key}";

        // 1. Cek cepat apakah sudah ada response hasil pemrosesan sebelumnya
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            if ($cached === 'PROCESSING') {
                return response()->json([
                    'message' => 'Transaksi Anda sedang diproses. Mohon jangan klik dua kali.',
                    'meta' => [
                        'idempotency_status' => 'BLOCKED_BY_LOCK',
                    ],
                ], 409);
            }

            if (is_array($cached)) {
                $status = $cached['status'] ?? 200;
                $body = $cached['body'] ?? [];

                return response()->json($body, $status)
                    ->header('X-Cache-Lookup', 'HIT_IDEMPOTENT');
            }
        }

        // 2. Gunakan Cache::add secara atomik (SETNX di Redis / insert unique constraint di database)
        // Jika return false, berarti request lain dengan key yang sama sudah melakukan lock/processing terlebih dahulu
        $locked = Cache::add($cacheKey, 'PROCESSING', 300);

        if (! $locked) {
            // Ambil ulang status terbaru karena lock gagal didapatkan
            $cached = Cache::get($cacheKey);

            if ($cached === 'PROCESSING' || $cached === null) {
                return response()->json([
                    'message' => 'Transaksi Anda sedang diproses. Mohon jangan klik dua kali.',
                    'meta' => [
                        'idempotency_status' => 'BLOCKED_BY_LOCK',
                    ],
                ], 409);
            }

            if (is_array($cached)) {
                $status = $cached['status'] ?? 200;
                $body = $cached['body'] ?? [];

                return response()->json($body, $status)
                    ->header('X-Cache-Lookup', 'HIT_IDEMPOTENT');
            }
        }

        try {
            $response = $next($request);
            $statusCode = $response->getStatusCode();

            // Jika sukses (200/201), simpan fotokopi array content dan HTTP status ke Redis dengan TTL 5 menit
            if ($statusCode === 200 || $statusCode === 201) {
                $content = json_decode($response->getContent(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $content = $response->getContent();
                }

                Cache::put($cacheKey, [
                    'status' => $statusCode,
                    'body' => $content,
                ], 300);
            } else {
                // Jika gagal, hapus key dari Redis via Cache::forget()
                Cache::forget($cacheKey);
            }

            return $response;
        } catch (\Throwable $e) {
            // Jika ada exception/gagal, hapus key dari Redis via Cache::forget()
            Cache::forget($cacheKey);
            throw $e;
        }
    }
}
