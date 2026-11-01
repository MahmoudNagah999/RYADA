<?php

namespace App\Http\Requests\Staff;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Staff\DTOs\InviteStaffData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::enum(RoleName::class)],
        ];
    }

    public function toDto(): InviteStaffData
    {
        return InviteStaffData::fromArray([
            ...$this->validated(),
            'locale' => app()->getLocale(),
        ]);
    }
}
