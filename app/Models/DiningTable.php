<?php

namespace App\Models;

use App\Enums\TableStatus;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['name', 'code', 'status', 'is_active'])]
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'available',
        'is_active' => true,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (DiningTable $diningTable): void {
            $diningTable->code ??= static::generateCode();
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
            'status' => TableStatus::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Generate a new unique, hard to guess QR code token.
     */
    public static function generateCode(): string
    {
        do {
            $code = Str::random(24);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get the customer ordering URL encoded in the QR code.
     */
    public function orderUrl(): string
    {
        return route('customer.menu', $this->code);
    }

    /**
     * Get all sessions for this table.
     *
     * @return HasMany<TableSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    /**
     * Alias for scoped route model binding of {tableSession}.
     *
     * @return HasMany<TableSession, $this>
     */
    public function tableSessions(): HasMany
    {
        return $this->sessions();
    }

    /**
     * Get the currently open session for this table, if any.
     *
     * @return HasOne<TableSession, $this>
     */
    public function activeSession(): HasOne
    {
        return $this->hasOne(TableSession::class)->whereNull('closed_at')->latestOfMany('opened_at');
    }

    /**
     * Determine whether the table can be safely deleted.
     */
    public function canBeDeleted(): bool
    {
        if ($this->status !== TableStatus::Available) {
            return false;
        }

        return ! $this->sessions()->exists();
    }
}
