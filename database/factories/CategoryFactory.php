<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(CategoryType::cases());
        $categoryData = $this->getCategoryDataByType($type);

        return [
            'name' => $categoryData['name'],
            'description' => $categoryData['description'],
            'type' => $type,
            'color' => $categoryData['color'],
            'icon' => $categoryData['icon'],
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'metadata' => $categoryData['metadata'] ?? null,

            // User stamps
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Get category data based on type.
     */
    private function getCategoryDataByType(CategoryType $type): array
    {
        return match ($type) {
            CategoryType::GENRE => $this->faker->randomElement([
                ['name' => 'Rock', 'color' => '#FF6B6B', 'icon' => 'fas fa-guitar', 'description' => 'A broad genre of popular music that originated as "rock and roll"'],
                ['name' => 'Jazz', 'color' => '#4ECDC4', 'icon' => 'fas fa-music', 'description' => 'A music genre that originated in the African-American communities'],
                ['name' => 'Metal', 'color' => '#45B7D1', 'icon' => 'fas fa-bolt', 'description' => 'A genre of rock music that developed in the late 1960s and early 1970s'],
                ['name' => 'Classical', 'color' => '#98D8C8', 'icon' => 'fas fa-violin', 'description' => 'Art music produced or rooted in Western musical traditions'],
                ['name' => 'Electronic', 'color' => '#BB8FCE', 'icon' => 'fas fa-microchip', 'description' => 'Music that employs electronic musical instruments'],
            ]),
            CategoryType::MOOD => $this->faker->randomElement([
                ['name' => 'Energetic', 'color' => '#FF6B6B', 'icon' => 'fas fa-bolt', 'description' => 'High energy and motivational music'],
                ['name' => 'Relaxing', 'color' => '#4ECDC4', 'icon' => 'fas fa-leaf', 'description' => 'Calm and peaceful music'],
                ['name' => 'Melancholic', 'color' => '#6C7CE0', 'icon' => 'fas fa-cloud-rain', 'description' => 'Sad and nostalgic music'],
                ['name' => 'Upbeat', 'color' => '#FFD93D', 'icon' => 'fas fa-sun', 'description' => 'Happy and cheerful music'],
            ]),
            CategoryType::THEME => $this->faker->randomElement([
                ['name' => 'Workout', 'color' => '#FF6B6B', 'icon' => 'fas fa-dumbbell', 'description' => 'Music for exercise and fitness'],
                ['name' => 'Study', 'color' => '#4ECDC4', 'icon' => 'fas fa-book', 'description' => 'Music for concentration and focus'],
                ['name' => 'Party', 'color' => '#FFD93D', 'icon' => 'fas fa-glass-cheers', 'description' => 'Music for celebrations and parties'],
                ['name' => 'Romance', 'color' => '#FF69B4', 'icon' => 'fas fa-heart', 'description' => 'Music for romantic moments'],
            ]),
            CategoryType::ERA => $this->faker->randomElement([
                ['name' => '1960s', 'color' => '#8B4513', 'icon' => 'fas fa-clock', 'description' => 'Music from the 1960s'],
                ['name' => '1980s', 'color' => '#FF1493', 'icon' => 'fas fa-clock', 'description' => 'Music from the 1980s'],
                ['name' => '2000s', 'color' => '#00CED1', 'icon' => 'fas fa-clock', 'description' => 'Music from the 2000s'],
                ['name' => '2020s', 'color' => '#32CD32', 'icon' => 'fas fa-clock', 'description' => 'Contemporary music from the 2020s'],
            ]),
            CategoryType::INSTRUMENT => $this->faker->randomElement([
                ['name' => 'Piano', 'color' => '#000000', 'icon' => 'fas fa-piano', 'description' => 'Piano-focused music'],
                ['name' => 'Guitar', 'color' => '#8B4513', 'icon' => 'fas fa-guitar', 'description' => 'Guitar-focused music'],
                ['name' => 'Orchestral', 'color' => '#FFD700', 'icon' => 'fas fa-music', 'description' => 'Orchestral and symphonic music'],
                ['name' => 'Electronic', 'color' => '#00FFFF', 'icon' => 'fas fa-microchip', 'description' => 'Electronic instrument music'],
            ]),
            CategoryType::LANGUAGE => $this->faker->randomElement([
                ['name' => 'English', 'color' => '#FF0000', 'icon' => 'fas fa-flag-usa', 'description' => 'English language music'],
                ['name' => 'Spanish', 'color' => '#FFFF00', 'icon' => 'fas fa-flag', 'description' => 'Spanish language music'],
                ['name' => 'Instrumental', 'color' => '#808080', 'icon' => 'fas fa-music', 'description' => 'Instrumental music without vocals'],
            ]),
            CategoryType::OCCASION => $this->faker->randomElement([
                ['name' => 'Wedding', 'color' => '#FFFFFF', 'icon' => 'fas fa-ring', 'description' => 'Music for wedding ceremonies'],
                ['name' => 'Birthday', 'color' => '#FFB6C1', 'icon' => 'fas fa-birthday-cake', 'description' => 'Music for birthday celebrations'],
                ['name' => 'Holiday', 'color' => '#228B22', 'icon' => 'fas fa-tree', 'description' => 'Music for holidays and festivals'],
            ]),
        };
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Category $category) {
            // Add meta tags based on category type and name
            $baseTags = ['music', 'category', $category->type->value];
            $specificTags = match ($category->type) {
                CategoryType::GENRE => match (strtolower($category->name)) {
                    'rock' => ['electric', 'guitar', 'drums'],
                    'jazz' => ['improvisation', 'swing', 'brass'],
                    'metal' => ['heavy', 'distortion', 'aggressive'],
                    'classical' => ['orchestral', 'symphony', 'instrumental'],
                    'electronic' => ['synthesizer', 'digital', 'beats'],
                    default => ['popular', 'mainstream'],
                },
                CategoryType::MOOD => ['emotion', 'feeling', 'atmosphere'],
                CategoryType::THEME => ['purpose', 'activity', 'context'],
                CategoryType::ERA => ['time', 'period', 'decade'],
                CategoryType::INSTRUMENT => ['musical', 'instrument', 'sound'],
                CategoryType::LANGUAGE => ['linguistic', 'vocal', 'cultural'],
                CategoryType::OCCASION => ['event', 'celebration', 'gathering'],
            };

            $category->attachTags(array_merge($baseTags, $specificTags));
        });
    }

    /**
     * Create a category with a specific type.
     */
    public function ofType(CategoryType $type): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => $type,
        ]);
    }

    /**
     * Create an active category.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive category.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a category as a child of another category (closure table).
     */
    public function childOf(Category $parent): static
    {
        return $this->afterCreating(function (Category $category) use ($parent) {
            $category->makeChildOf($parent);
        });
    }

    /**
     * Create a root category (no parents).
     */
    public function root(): static
    {
        return $this->afterCreating(function (Category $category) {
            // Root categories don't need special handling in closure table
            // They simply won't have any parent relationships
        });
    }
}
