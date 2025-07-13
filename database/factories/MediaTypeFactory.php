<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MediaType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MediaType>
 */
class MediaTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = MediaType::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'MPEG audio file',
                'Protected AAC audio file',
                'Protected MPEG-4 video file',
                'Purchased AAC audio file',
                'AAC audio file',
                'FLAC audio file',
                'WAV audio file',
                'OGG audio file',
            ]),
        ];
    }
}
