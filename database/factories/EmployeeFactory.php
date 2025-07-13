<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'last_name' => $this->faker->lastName(),
            'first_name' => $this->faker->firstName(),
            'title' => $this->faker->randomElement([
                'General Manager',
                'Sales Manager',
                'Sales Support Agent',
                'IT Manager',
                'IT Staff',
                'Customer Service Representative',
                'Marketing Manager',
                'Accountant',
            ]),
            'reports_to' => null, // Will be set by relationships
            'birth_date' => $this->faker->dateTimeBetween('-65 years', '-25 years'),
            'hire_date' => $this->faker->dateTimeBetween('-20 years', '-1 year'),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'country' => $this->faker->country(),
            'postal_code' => $this->faker->postcode(),
            'phone' => $this->faker->phoneNumber(),
            'fax' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }

    /**
     * Create a manager employee.
     */
    public function manager(): static
    {
        return $this->state(fn(array $attributes) => [
            'title' => $this->faker->randomElement([
                'General Manager',
                'Sales Manager',
                'IT Manager',
                'Marketing Manager',
            ]),
        ]);
    }

    /**
     * Create an employee that reports to a manager.
     */
    public function reportsTo(Employee $manager): static
    {
        return $this->state(fn(array $attributes) => [
            'reports_to' => $manager->id,
            'title' => $this->faker->randomElement([
                'Sales Support Agent',
                'IT Staff',
                'Customer Service Representative',
                'Accountant',
            ]),
        ]);
    }
}
