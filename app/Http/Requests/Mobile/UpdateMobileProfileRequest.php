<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMobileProfileRequest extends FormRequest
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
            'phone'     => [
                'required',
                'string',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'email'     => [ // Optional for patients, but allowed
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            // Add password if you allow changing it here, OR keep it separate
        ];
    }
}
