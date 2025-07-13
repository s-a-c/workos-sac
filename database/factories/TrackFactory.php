<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Album;
use App\Models\Genre;
use App\Models\MediaType;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Track>
 */
class TrackFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Track::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(rand(1, 5), false),
            'album_id' => Album::factory(),
            'media_type_id' => MediaType::factory(),
            'genre_id' => Genre::factory(),
            'composer' => $this->faker->optional(0.7)->name(),
            'milliseconds' => $this->faker->numberBetween(30000, 600000), // 30 seconds to 10 minutes
            'bytes' => $this->faker->numberBetween(1000000, 50000000),    // 1MB to 50MB
            'unit_price' => $this->faker->randomElement(['0.99', '1.29', '1.99']),
        ];
    }

    /**
     * Create a track for a specific album.
     */
    public function forAlbum(Album $album): static
    {
        return $this->state(fn(array $attributes) => [
            'album_id' => $album->id,
        ]);
    }

    /**
     * Create a short track (under 3 minutes).
     */
    public function short(): static
    {
        return $this->state(fn(array $attributes) => [
            'milliseconds' => $this->faker->numberBetween(30000, 180000),
        ]);
    }

    /**
     * Create a long track (over 6 minutes).
     */
    public function long(): static
    {
        return $this->state(fn(array $attributes) => [
            'milliseconds' => $this->faker->numberBetween(360000, 1200000),
        ]);
    }
}
