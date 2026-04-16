<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\PaymentProof;
use App\Support\ChargeStatusTransition;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PaymentProofService
{
    public function uploadProof(Charge $charge, UploadedFile $file): PaymentProof
    {
        if (! in_array($charge->status, ['pending', 'rejected'], true)) {
            throw new \DomainException('Cannot upload proof for this charge status.');
        }

        if ($charge->paymentProofs()->exists() && $charge->status !== 'rejected') {
            throw new \DomainException('Comprovante ja enviado.');
        }

        $previousStatus = $charge->status;

        $path = $file->store("payment-proofs/{$charge->id}", 'local');

        $proof = PaymentProof::create([
            'charge_id' => $charge->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'status' => 'pending',
        ]);

        if ($previousStatus === 'rejected') {
            ChargeStatusTransition::assertTransition('rejected', 'pending');
            $charge->update(['status' => 'pending']);
        }

        return $proof;
    }

    public function getProofPath(PaymentProof $proof): string
    {
        return Storage::disk('local')->path($proof->file_path);
    }
}
