<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\NormalizesParticipantFormInput;
use Illuminate\Foundation\Http\FormRequest;

class AddPublicExpenseParticipantsRequest extends FormRequest
{
    use NormalizesParticipantFormInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'participants' => ['sometimes', 'array'],
            'participants.*.name' => ['required_with:participants.*.phone', 'string', 'max:255'],
            'participants.*.phone' => ['required_with:participants.*.name', 'string', 'max:32'],
            'participants_text' => ['nullable', 'string', 'max:20000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'participants' => $this->buildNormalizedParticipantsList(
                $this->input('participants'),
                (string) $this->input('participants_text', ''),
            ),
        ]);
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
