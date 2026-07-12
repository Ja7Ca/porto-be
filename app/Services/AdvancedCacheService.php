<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AdvancedCacheService
{
    // Penanda khusus untuk data yang memang tidak ada di DB (Anti-Penetration)
    private const CACHE_NULL_VALUE = '||NOT_FOUND||';

    /**
     * Mengambil data dari cache dengan proteksi tingkat lanjut (Anti-Stampede & Anti-Penetration)
     *
     * @param  string  $cacheKey  Kunci unik Redis
     * @param  int  $ttlSeconds  Waktu simpan data asli (dalam detik)
     * @param  \Closure  $dbQueryFuntion  Fungsi query ke database
     * @return mixed
     */
    public function getOrSet(string $cacheKey, int $ttlSeconds, \Closure $dbQueryFuntion)
    {
        // 1. Cek Cache Utama di Redis
        $cachedData = Cache::get($cacheKey);

        if ($cachedData !== null) {
            if ($cachedData === self::CACHE_NULL_VALUE) {
                return null;
            }

            return $cachedData;
        }

        // 2. Antisipasi Cache Stampede: Buat Mekanisme Mutex Lock
        $lockKey = "lock:{$cacheKey}";
        $lockTimeoutSeconds = 5;

        // Menggunakan Laravel Cache Atomic Lock
        $lock = Cache::lock($lockKey, $lockTimeoutSeconds);

        if ($lock->get()) {
            try {
                $dbData = $dbQueryFuntion();

                if ($dbData !== null) {
                    Cache::put($cacheKey, $dbData, $ttlSeconds);

                    return $dbData;
                } else {
                    $antiPenetrationTtl = 300;
                    Cache::put($cacheKey, self::CACHE_NULL_VALUE, $antiPenetrationTtl);

                    return null;
                }
            } finally {
                $lock->release();
            }
        } else {
            // Tunda sejenak (50 milidetik)
            usleep(50000);

            // Rekursi
            return $this->getOrSet($cacheKey, $ttlSeconds, $dbQueryFuntion);
        }
    }
}
