<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(20, 150) * 1000;

        return [
            'table_session_id' => TableSession::factory(),
            'recorded_by_user_id' => null,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'amount' => $amount,
            'amount_received' => $amount,
            'change_amount' => 0,
            'paid_at' => now(),
        ];
    }
}
