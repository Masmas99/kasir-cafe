<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table): void {
            $table->string('customer_name')->nullable()->after('token');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_name')->nullable()->after('order_number');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['customer_name', 'customer_phone']);
        });

        Schema::table('table_sessions', function (Blueprint $table): void {
            $table->dropColumn(['customer_name', 'customer_phone']);
        });
    }
};
