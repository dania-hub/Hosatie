<?php
namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class ResetMobilePasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'phone'    => 'required|exists:users,phone',
        'otp'      => 'required|string|size:6',
        'password' => 'required|string|min:8|confirmed',
        ];
    }
}
