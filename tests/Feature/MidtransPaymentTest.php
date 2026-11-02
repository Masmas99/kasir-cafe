<?php

namespace Tests\Feature;

use App\Actions\CreateMidtransQrisPayment;
use App\Actions\PlaceOrder;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\ActivityLog;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Services\Midtrans\MidtransClient;
use App\Services\Midtrans\MidtransSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'midtrans.merchant_id' => 'G123456789',
            'midtrans.client_key' => 'SB-Mid-client-test123',
            'midtrans.server_key' => 'SB-Mid-server-test123',
            'midtrans.is_production' => false,
            'midtrans.sandbox_base_url' => 'https://api.sandbox.midtrans.com',
        ]);
    }

    public function test_can_create_midtrans_qris_charge_for_table_session(): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'status_message' => 'QRIS transaction is created',
                'transaction_id' => 'midtrans-tx-001',
                'transaction_status' => 'pending',
                'payment_type' => 'qris',
                'qr_string' => '00020101021226590014ID.CO.MIDTRANS.WWW0118936005030000089824',
                'actions' => [
                    [
                        'name' => 'generate-qr-code',
                        'method' => 'GET',
                        'url' => 'https://api.sandbox.midtrans.com/v2/qris/midtrans-tx-001/qr-code',
                    ],
                ],
            ], 200),
        ]);

        $table = DiningTable::factory()->create(['name' => 'Meja 2']);
        $menuItem = MenuItem::factory()->create(['price' => 30000]);

        $order = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 2],
        ]);

        $session = $order->tableSession;

        $payment = app(CreateMidtransQrisPayment::class)->handle(
            $session,
            app(MidtransClient::class),
        );

        $this->assertSame(PaymentMethod::Qris, $payment->method);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(60000, $payment->amount);
        $this->assertSame(
            '00020101021226590014ID.CO.MIDTRANS.WWW0118936005030000089824',
            $payment->payload['qr_string'] ?? null,
        );
        $this->assertSame(PaymentStatus::Pending, $session->refresh()->payment_status);
    }

    public function test_falls_back_to_snap_qris_charge_when_core_api_channel_is_not_activated(): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '402',
                'status_message' => 'Payment channel is not activated.',
            ], 200),
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-123',
            ], 201),
            'https://app.sandbox.midtrans.com/snap/v1/transactions/snap-token-123/pay' => Http::response([
                'status_code' => '201',
                'transaction_id' => 'snap-qris-tx-001',
                'transaction_status' => 'pending',
                'qr_string' => '00020101021226620014COM.GO-JEK.WWW01199360091431275173900',
                'qris_url' => 'https://merchants-app.sbx.midtrans.com/v4/qris/gopay/A120261102211524/qr-code',
            ], 200),
        ]);

        $table = DiningTable::factory()->create(['name' => 'Meja 3']);
        $menuItem = MenuItem::factory()->create(['price' => 25000]);

        $order = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);

        $payment = app(CreateMidtransQrisPayment::class)->handle(
            $order->tableSession,
            app(MidtransClient::class),
        );

        $this->assertSame(
            '00020101021226620014COM.GO-JEK.WWW01199360091431275173900',
            $payment->payload['qr_string'] ?? null,
        );
        $this->assertSame(
            'https://merchants-app.sbx.midtrans.com/v4/qris/gopay/A120261102211524/qr-code',
            $payment->payment_url,
        );
    }

    public function test_valid_midtrans_webhook_marks_session_and_table_paid_automatically_and_is_idempotent(): void
    {
        $table = DiningTable::factory()->create(['name' => 'Meja 5']);
        $menuItem = MenuItem::factory()->create(['price' => 45000]);

        $order = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);

        $session = $order->tableSession;

        $payment = Payment::factory()->create([
            'table_session_id' => $session->id,
            'invoice_number' => 'INV-MID-20261103-0001',
            'method' => PaymentMethod::Qris,
            'status' => PaymentStatus::Pending,
            'amount' => 45000,
            'paid_at' => null,
        ]);

        $orderId = 'INV-MID-20261103-0001';
        $statusCode = '200';
        $grossAmount = '45000.00';

        $signature = MidtransSignature::generate(
            $orderId,
            $statusCode,
            $grossAmount,
            'SB-Mid-server-test123',
        );

        $payload = [
            'transaction_id' => 'midtrans-tx-999',
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'payment_type' => 'qris',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'signature_key' => $signature,
        ];

        $this->postJson(route('webhooks.midtrans.notification'), $payload)
            ->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $session->refresh()->payment_status);
        $this->assertSame(TableStatus::Available, $table->refresh()->status);

        // Second identical callback must be idempotent
        $this->postJson(route('webhooks.midtrans.notification'), $payload)
            ->assertOk()
            ->assertJson(['message' => 'Already processed.']);

        $this->assertSame(1, Payment::where('table_session_id', $session->id)->count());
        $this->assertSame(1, ActivityLog::where('action', 'midtrans_payment_succeeded')->count());
    }

    public function test_invalid_midtrans_signature_is_rejected(): void
    {
        $this->postJson(route('webhooks.midtrans.notification'), [
            'order_id' => 'INV-FAKE',
            'status_code' => '200',
            'gross_amount' => '25000.00',
            'transaction_status' => 'settlement',
            'signature_key' => 'invalid-signature',
        ])->assertUnauthorized();
    }

    public function test_expired_midtrans_qris_keeps_session_unpaid_and_allows_retry(): void
    {
        $table = DiningTable::factory()->create(['name' => 'Meja 6']);
        $menuItem = MenuItem::factory()->create(['price' => 25000]);

        $order = app(PlaceOrder::class)->handle($table, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);

        $session = $order->tableSession;

        $payment = Payment::factory()->create([
            'table_session_id' => $session->id,
            'invoice_number' => 'INV-MID-EXP-01',
            'method' => PaymentMethod::Qris,
            'status' => PaymentStatus::Pending,
            'amount' => 25000,
            'paid_at' => null,
        ]);

        $signature = MidtransSignature::generate(
            'INV-MID-EXP-01',
            '202',
            '25000.00',
            'SB-Mid-server-test123',
        );

        $this->postJson(route('webhooks.midtrans.notification'), [
            'order_id' => 'INV-MID-EXP-01',
            'status_code' => '202',
            'gross_amount' => '25000.00',
            'transaction_status' => 'expire',
            'signature_key' => $signature,
        ])->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->refresh()->status);
        $this->assertFalse($session->refresh()->isPaid());
        $this->assertSame(TableStatus::Occupied, $table->refresh()->status);

        Http::fake([
            'https://api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'transaction_status' => 'pending',
                'payment_type' => 'qris',
                'qr_string' => '00020101021226590014ID.CO.MIDTRANS.RETRY2',
            ], 200),
        ]);

        $retried = app(CreateMidtransQrisPayment::class)->handle($session, app(MidtransClient::class));

        $this->assertNotSame($payment->id, $retried->id);
        $this->assertSame(PaymentStatus::Pending, $retried->status);
    }
}
