<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $manageToken = $request->query('manage');
        $manageOk = $manageToken && hash_equals((string) $this->manage_token, (string) $manageToken);

        return [
            'id' => $this->id,
            'public_hash' => $this->public_hash,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'amount_per_member' => $this->amount_per_member,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'pix_key' => $this->pix_key,
            'pix_qr_code' => $this->pix_qr_code,
            'owner_name' => $this->owner_name,
            'owner_phone' => $manageOk ? $this->owner_phone : $this->maskPhone($this->owner_phone),
            'can_manage' => $manageOk,
            'members' => $this->whenLoaded('charges', fn () => $this->charges->map(function ($charge) use ($manageOk) {
                $member = $charge->teamMember;
                $row = [
                    'id' => $member?->id,
                    'name' => $member?->name,
                    'phone' => $member?->phone,
                    'charge_id' => $charge->id,
                    'charge_status' => $charge->status,
                    'amount' => $charge->amount,
                ];
                if ($manageOk && $member?->unique_hash) {
                    $row['participant_url'] = $this->participantUrl($member->unique_hash);
                }

                return $row;
            })),
        ];
    }

    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) < 4) {
            return '***';
        }

        return '***'.substr($digits, -4);
    }

    private function participantUrl(string $participantHash): string
    {
        return rtrim((string) config('app.url'), '/').'/p/'.$this->public_hash.'/'.$participantHash;
    }
}
