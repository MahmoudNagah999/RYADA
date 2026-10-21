<?php

namespace App\Http\Requests\Auth;

use App\Domain\Auth\DTOs\LoginData;
use App\Domain\Auth\Enums\TokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::enum(TokenAbility::class)],
        ];
    }

    public function toDto(): LoginData
    {
        /** @var array<int, string> $abilities */
        $abilities = $this->input('abilities', ['*']);

        return new LoginData(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            deviceName: $this->string('device_name', 'api')->toString(),
            abilities: $abilities,
        );
    }
}
