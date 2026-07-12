<?php

namespace Database\Seeders;

use App\Models\MockProduct;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $totalData = 100000;
        $batchSize = 5000;

        $this->command->info('Memulai menyuntikkan 100.000 data palsu...');

        for ($i = 0; $i < $totalData; $i += $batchSize) {
            MockProduct::factory()->count($batchSize)->create();

            $this->command->info('Berhasil menyuntikkan '.($i + $batchSize).' data.');
        }

        $this->command->info('Selesai! Kolam sandbox Anda sudah siap.');
    }
}
