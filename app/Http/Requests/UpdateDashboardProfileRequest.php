<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'full_name' => 'required|string|max:255',
            'email'     => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone'     => [ // Staff might have a phone too
                'required',
                'string',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            // Staff might not be allowed to change their own National ID, so we omit it
        ];
    }
}
