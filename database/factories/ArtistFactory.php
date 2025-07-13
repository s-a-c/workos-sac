<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Artist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Artist>
 */
class ArtistFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Artist::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                    'The Beatles',
                    'Led Zeppelin',
                    'Pink Floyd',
                    'Queen',
                    'The Rolling Stones',
                    'AC/DC',
                    'Metallica',
                    'Nirvana',
                    'Radiohead',
                    'U2',
                    'Bob Dylan',
                    'The Who',
                    'David Bowie',
                    'Jimi Hendrix',
                    'The Doors',
                ]) ?? $this->faker->company() . ' Band',

            // Enhanced fields
            'biography' => $this->faker->optional(0.7)->paragraphs(3, true),
            'website' => $this->faker->optional(0.5)->url(),
            'social_links' => $this->faker->optional(0.6)->randomElement([
                [
                    'twitter' => 'https://twitter.com/' . $this->faker->userName(),
                    'instagram' => 'https://instagram.com/' . $this->faker->userName(),
                    'facebook' => 'https://facebook.com/' . $this->faker->userName(),
                ],
                [
                    'spotify' => 'https://open.spotify.com/artist/' . $this->faker->uuid(),
                    'youtube' => 'https://youtube.com/c/' . $this->faker->userName(),
                ],
            ]),

            // User stamps (will be set automatically by the trait)
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Artist $artist) {
            // Add realistic tags
            $tags = $this->faker->randomElements([
                'rock', 'classic-rock', 'hard-rock', 'progressive-rock',
                'jazz', 'blues', 'folk', 'country', 'pop', 'alternative',
                'metal', 'heavy-metal', 'punk', 'indie', 'electronic',
                'soul', 'funk', 'reggae', 'hip-hop', 'classical',
            ], $this->faker->numberBetween(1, 4));

            $artist->attachTags($tags);
        });
    }

    /**
     * Create a rock artist.
     */
    public function rock(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => $this->faker->randomElement([
                'Iron Maiden',
                'Black Sabbath',
                'Deep Purple',
                'Judas Priest',
                'Motorhead',
                'Ozzy Osbourne',
                'Dio',
                'Rainbow',
            ]),
        ])->afterCreating(function (Artist $artist) {
            $artist->syncTags(['rock', 'hard-rock', 'metal']);
        });
    }

    /**
     * Create a jazz artist.
     */
    public function jazz(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => $this->faker->randomElement([
                'Miles Davis',
                'John Coltrane',
                'Duke Ellington',
                'Charlie Parker',
                'Thelonious Monk',
                'Bill Evans',
                'Herbie Hancock',
                'Wayne Shorter',
            ]),
        ])->afterCreating(function (Artist $artist) {
            $artist->syncTags(['jazz', 'blues', 'instrumental']);
        });
    }

    /**
     * Create a popular artist with enhanced data.
     */
    public function popular(): static
    {
        return $this->state(fn(array $attributes) => [
            'biography' => $this->faker->paragraphs(5, true),
            'website' => $this->faker->url(),
            'social_links' => [
                'twitter' => 'https://twitter.com/' . $this->faker->userName(),
                'instagram' => 'https://instagram.com/' . $this->faker->userName(),
                'facebook' => 'https://facebook.com/' . $this->faker->userName(),
                'spotify' => 'https://open.spotify.com/artist/' . $this->faker->uuid(),
                'youtube' => 'https://youtube.com/c/' . $this->faker->userName(),
                'website' => $this->faker->url(),
            ],
        ]);
    }
}
