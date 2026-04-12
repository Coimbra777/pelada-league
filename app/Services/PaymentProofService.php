<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\PaymentProof;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PaymentProofService
{
    public function uploadProof(Charge $charge, UploadedFile $file): PaymentProof
    {
        if (!in_array($charge->status, ['pending', 'rejected'])) {
            throw new \DomainException('Cannot upload proof for this charge status.');
        }

        $path = $file->store("payment-proofs/{$charge->id}", 'local');

        return PaymentProof::create([
            'charge_id' => $charge->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'status' => 'pending',
        ]);
    }

    public function getProofPath(PaymentProof $proof): string
    {
        return Storage::disk('local')->path($proof->file_path);
    }
}
