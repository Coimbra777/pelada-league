<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'unique_hash',
        'user_id',
        'name',
        'phone',
        'email',
        'role',
    ];

    protected static function booted(): void
    {
        static::creating(function (TeamMember $member) {
            if (empty($member->unique_hash)) {
                $member->unique_hash = (string) Str::uuid();
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
