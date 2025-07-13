<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Album>
 */
class AlbumFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Album::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(rand(1, 4), false),
            'artist_id' => Artist::factory(),
        ];
    }

    /**
     * Create an album for an existing artist.
     */
    public function forArtist(Artist $artist): static
    {
        return $this->state(fn(array $attributes) => [
            'artist_id' => $artist->id,
        ]);
    }

    /**
     * Create a classic rock album.
     */
    public function classicRock(): static
    {
        return $this->state(fn(array $attributes) => [
            'title' => $this->faker->randomElement([
                'Dark Side of the Moon',
                'Led Zeppelin IV',
                'Abbey Road',
                'The Wall',
                'Back in Black',
                'Rumours',
                'Hotel California',
                'Born to Run',
            ]),
        ]);
    }
}
