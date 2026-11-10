<?php

namespace App\Domain\Shared\Tenancy\Queue;

/**
 * A queued class that works on one club's data. It carries the club id (never a tenant-owned model).
 */
interface TenantAwareJob
{
    public function clubId(): int;
}
