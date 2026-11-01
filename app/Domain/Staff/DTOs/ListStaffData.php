<?php

namespace App\Domain\Staff\DTOs;

use App\Domain\Auth\Enums\RoleName;

final readonly class ListStaffData
{
    public function __construct(
        public ?RoleName $role,
        public ?string $search,
        public string $sort,
        public string $direction,
        public int $perPage,
        public int $page,
    ) {}

    /**
     * @param  array{role?: string|null, search?: string|null, sort?: string, direction?: string, per_page?: int, page?: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            role: isset($data['role']) ? RoleName::from($data['role']) : null,
            search: $data['search'] ?? null,
            sort: $data['sort'] ?? 'name',
            direction: $data['direction'] ?? 'asc',
            perPage: $data['per_page'] ?? 25,
            page: $data['page'] ?? 1,
        );
    }
}
