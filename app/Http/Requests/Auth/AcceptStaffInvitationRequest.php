<?php

namespace App\Http\Requests\Auth;

use App\Domain\Staff\DTOs\AcceptStaffInvitationData;
use Illuminate\Foundation\Http\FormRequest;

class AcceptStaffInvitationRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:256'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function toDto(): AcceptStaffInvitationData
    {
        return AcceptStaffInvitationData::fromArray([
            ...$this->validated(),
            'locale' => app()->getLocale(),
        ]);
    }
}
