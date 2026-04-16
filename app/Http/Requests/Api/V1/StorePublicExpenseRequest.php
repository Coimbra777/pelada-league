<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\NormalizesParticipantFormInput;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicExpenseRequest extends FormRequest
{
    use NormalizesParticipantFormInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_phone' => ['required', 'string', 'max:32'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:1'],
            'pix_key' => ['required', 'string', 'max:255'],
            'pix_qr_code' => ['nullable', 'string'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'include_owner_as_participant' => ['sometimes', 'boolean'],
            'participants' => ['sometimes', 'array'],
            'participants.*.name' => ['required_with:participants.*.phone', 'string', 'max:255'],
            'participants.*.phone' => ['required_with:participants.*.name', 'string', 'max:32'],
            'participants_text' => ['nullable', 'string', 'max:20000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('owner_phone')) {
            $this->merge([
                'owner_phone' => PhoneNormalizer::digits($this->input('owner_phone')),
            ]);
        }

        $merged = $this->buildNormalizedParticipantsList(
            $this->input('participants'),
            (string) $this->input('participants_text', ''),
        );

        $seen = [];
        foreach ($merged as $item) {
            $seen[$item['phone']] = true;
        }

        if ($this->boolean('include_owner_as_participant')) {
            $ownerPhone = PhoneNormalizer::digits((string) $this->input('owner_phone'));
            $ownerName = trim((string) $this->input('owner_name'));
            if ($ownerPhone !== '' && strlen($ownerPhone) >= 10 && $ownerName !== '' && ! isset($seen[$ownerPhone])) {
                array_unshift($merged, ['name' => $ownerName, 'phone' => $ownerPhone]);
                $seen[$ownerPhone] = true;
            }
        }

        $this->merge(['participants' => $merged]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (count($this->input('participants', [])) < 1) {
                $validator->errors()->add('participants', 'Informe ao menos um participante com nome e telefone valido.');
            }
        });
    }
}
