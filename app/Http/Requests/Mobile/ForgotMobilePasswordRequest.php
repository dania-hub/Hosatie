<?php
namespace App\Http\Requests\Mobile; // Put in Mobile folder if you like

use Illuminate\Foundation\Http\FormRequest;

class ForgotMobilePasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return ['phone' => 'required|exists:users,phone'];
    }
}
