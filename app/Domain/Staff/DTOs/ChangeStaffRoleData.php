<?php

namespace App\Domain\Staff\DTOs;

use App\Domain\Auth\Enums\RoleName;

final readonly class ChangeStaffRoleData
{
    public function __construct(public RoleName $role) {}

    /**
     * @param  array{role: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(role: RoleName::from($data['role']));
    }
}
