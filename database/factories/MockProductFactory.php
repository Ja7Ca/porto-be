<?php

namespace Database\Factories;

use App\Models\MockProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockProduct>
 */
class MockProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Kumpulan kategori produk tiruan untuk variasi nama
        $categories = ['Sepatu', 'Baju', 'Celana', 'Tas', 'Topi', 'Jaket', 'Dompet'];
        $adjectives = ['Elit', 'Premium', 'Minimalis', 'Eksklusif', 'Urban', 'Sporty'];

        return [
            // Menghasilkan nama seperti: "Sepatu Premium Elit" atau "Tas Minimalis"
            'name' => $this->faker->randomElement($categories).' '.
                      $this->faker->randomElement($adjectives).' '.
                      $this->faker->word,

            'price' => $this->faker->numberBetween(10000, 2000000), // Rp 10rb - Rp 2jt
            'stok' => $this->faker->numberBetween(10, 100),
            'description' => $this->faker->paragraph(3), // Deskripsi 3 kalimat
            'sku' => 'PROD-'.$this->faker->unique()->numberBetween(100000, 999999),
        ];
    }
}
