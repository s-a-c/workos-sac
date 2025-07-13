<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'company' => $this->faker->optional(0.3)->company(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->optional(0.7)->stateAbbr(),
            'country' => $this->faker->country(),
            'postal_code' => $this->faker->postcode(),
            'phone' => $this->faker->phoneNumber(),
            'fax' => $this->faker->optional(0.2)->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'support_rep_id' => Employee::factory(),
        ];
    }

    /**
     * Create a customer with a specific support representative.
     */
    public function withSupportRep(Employee $employee): static
    {
        return $this->state(fn(array $attributes) => [
            'support_rep_id' => $employee->id,
        ]);
    }
}
