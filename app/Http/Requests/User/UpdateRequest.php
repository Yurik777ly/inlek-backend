<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    const MAX_AGE = 105;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {      
        return [
            'phone' => ['string',  Rule::unique('users')->ignore($this->user()->id), 'regex:/^\+375(25|29|33|44)\-\d{3}\-\d{2}\-\d{2}$/'],
            'first_name' => ['nullable', 'string','min:2','regex:/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u'],
            'last_name' => ['nullable', 'string', 'min:2','regex:/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u'],
            'gender' => ['nullable', 'string'],
            'birthday' => ['nullable', 'date', 'before_or_equal:' . now()->format('Y-m-d'), 'after_or_equal:' . now()->subYears(self::MAX_AGE)->format('Y-m-d')],
            'email' => ['nullable', 'email'],
            'old_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string'],
            'new_password_confirm' => ['nullable', 'string'],
            'status_notifications' => ['nullable', 'string'],
            'accept_policy' => ['string'],
            'code' => ['nullable', 'string'],
        ];
    }
}
