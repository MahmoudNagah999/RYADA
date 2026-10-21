<?php

namespace App\Http\Requests\Club;

use App\Domain\Club\DTOs\RegisterClubData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterClubRequest extends FormRequest
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
            'club_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'locale' => ['sometimes', 'string', Rule::in(config('locales.supported'))],
        ];
    }

    public function toDto(): RegisterClubData
    {
        return new RegisterClubData(
            clubName: $this->string('club_name')->toString(),
            ownerName: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            // Default to the locale already resolved by SetLocale for this request.
            locale: $this->string('locale', app()->getLocale())->toString(),
        );
    }
}
