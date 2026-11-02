<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\TableSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['dining_table_id', 'token', 'customer_name', 'customer_phone', 'payment_status', 'total_amount', 'opened_at', 'paid_at', 'closed_at', 'closed_by'])]
class TableSession extends Model
{
    /** @use HasFactory<TableSessionFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payment_status' => 'unpaid',
        'total_amount' => 0,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (TableSession $session): void {
            $session->token ??= Str::random(32);
            $session->opened_at ??= now();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'total_amount' => 'integer',
            'opened_at' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Get the dining table for this session.
     *
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    /**
     * Get the user who closed the session.
     *
     * @return BelongsTo<User, $this>
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the orders placed in this session.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the payments recorded for this session.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope the query to open (active) table sessions.
     *
     * @param  Builder<TableSession>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    /**
     * Determine whether the session is still open.
     */
    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Determine whether the session bill has been paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    /**
     * Recalculate and persist the total amount across all non-cancelled orders and items.
     */
    public function recalculateTotal(): void
    {
        foreach ($this->orders()->get() as $order) {
            if ($order->status === OrderStatus::Cancelled) {
                $order->update(['total_amount' => 0]);

                continue;
            }

            $orderTotal = (int) $order->items()
                ->where('status', OrderItemStatus::Active)
                ->sum('subtotal');

            $order->update(['total_amount' => $orderTotal]);
        }

        $sessionTotal = (int) $this->orders()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->sum('total_amount');

        $this->update(['total_amount' => $sessionTotal]);
    }
}
