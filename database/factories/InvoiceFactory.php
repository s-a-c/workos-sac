<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'invoice_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'billing_address' => $this->faker->streetAddress(),
            'billing_city' => $this->faker->city(),
            'billing_state' => $this->faker->optional(0.7)->stateAbbr(),
            'billing_country' => $this->faker->country(),
            'billing_postal_code' => $this->faker->postcode(),
            'total' => $this->faker->randomFloat(2, 0.99, 99.99),
        ];
    }

    /**
     * Create an invoice for a specific customer.
     */
    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn(array $attributes) => [
            'customer_id' => $customer->id,
            'billing_address' => $customer->address,
            'billing_city' => $customer->city,
            'billing_state' => $customer->state,
            'billing_country' => $customer->country,
            'billing_postal_code' => $customer->postal_code,
        ]);
    }
}
