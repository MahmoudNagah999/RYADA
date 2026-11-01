<?php

namespace App\Http\Requests\Staff;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Staff\DTOs\ChangeStaffRoleData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStaffRoleRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::enum(RoleName::class)],
        ];
    }

    public function toDto(): ChangeStaffRoleData
    {
        return ChangeStaffRoleData::fromArray($this->validated());
    }
}
