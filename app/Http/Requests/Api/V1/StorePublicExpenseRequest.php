<?php

namespace App\Http\Requests\Api\V1;

use App\Support\ParticipantListParser;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicExpenseRequest extends FormRequest
{
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

        $fromArray = [];
        $participants = $this->input('participants');
        if (is_array($participants)) {
            foreach ($participants as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $fromArray[] = [
                    'name' => trim((string) ($p['name'] ?? '')),
                    'phone' => PhoneNormalizer::digits($p['phone'] ?? ''),
                ];
            }
        }

        $fromText = ParticipantListParser::parse((string) $this->input('participants_text', ''));

        $merged = [];
        $seen = [];
        foreach (array_merge($fromArray, $fromText) as $item) {
            $phone = $item['phone'] ?? '';
            $name = $item['name'] ?? '';
            if ($phone === '' || strlen($phone) < 10 || $name === '') {
                continue;
            }
            if (isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $merged[] = ['name' => $name, 'phone' => $phone];
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
