<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSession>
 */
class TableSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dining_table_id' => DiningTable::factory(),
            'payment_status' => PaymentStatus::Unpaid,
            'total_amount' => 0,
            'opened_at' => now(),
        ];
    }

    /**
     * Indicate that the session has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the session has been closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'closed_at' => now(),
        ]);
    }
}
