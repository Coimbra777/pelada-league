<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'created_by',
        'description',
        'total_amount',
        'amount_per_member',
        'due_date',
        'pix_key',
        'pix_qr_code',
        'status',
        'public_hash',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'amount_per_member' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function getPublicUrl(): string
    {
        return config('app.url') . '/p/' . $this->public_hash;
    }

    public function scopeByHash($query, string $hash)
    {
        return $query->where('public_hash', $hash);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    private const PAID_STATUSES = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'];

    public function recalculateStatus(): void
    {
        $charges = $this->charges()->get();

        if ($charges->isEmpty()) {
            return;
        }

        $allPaid = $charges->every(fn ($c) => in_array($c->status, self::PAID_STATUSES));
        $somePaid = $charges->contains(fn ($c) => in_array($c->status, self::PAID_STATUSES));
        $anyOverdue = $charges->contains(fn ($c) => $c->status === 'OVERDUE');

        if ($allPaid) {
            $newStatus = 'PAID';
        } elseif ($somePaid) {
            $newStatus = 'PARTIALLY_PAID';
        } elseif ($anyOverdue) {
            $newStatus = 'OVERDUE';
        } else {
            $newStatus = 'open';
        }

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}
