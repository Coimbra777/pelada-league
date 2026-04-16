<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:5'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'pix_key' => ['required', 'string', 'max:255'],
            'pix_qr_code' => ['nullable', 'string'],
        ];
    }
}
