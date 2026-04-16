<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\NormalizesParticipantFormInput;
use App\Models\Expense;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class AddTeamExpenseParticipantsRequest extends FormRequest
{
    use NormalizesParticipantFormInput;

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
