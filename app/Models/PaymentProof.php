<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    use HasFactory;

    protected $fillable = [
        'charge_id',
        'file_path',
        'original_filename',
        'mime_type',
        'extracted_data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
        ];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
