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
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
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
