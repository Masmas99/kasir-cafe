<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $table = DiningTable::factory();

        return [
            'table_session_id' => TableSession::factory()->for($table),
            'dining_table_id' => $table,
            'placed_by_user_id' => null,
            'source' => 'qr',
            'status' => OrderStatus::Pending,
            'total_amount' => 0,
        ];
    }
}
