<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Expense;
use App\Models\Team;
use App\Support\ParticipantListParser;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class AddTeamExpenseParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();
        if (! $user) {
            return false;
        }

        /** @var Team|null $team */
        $team = $this->route('team');
        /** @var Expense|null $expense */
        $expense = $this->route('expense');
        if (! $team instanceof Team || ! $expense instanceof Expense) {
            return false;
        }
        if ((int) $expense->team_id !== (int) $team->id) {
            return false;
        }

        $membership = $team->members()->where('user_id', $user->id)->first();

        return $membership && $membership->role === 'admin';
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
