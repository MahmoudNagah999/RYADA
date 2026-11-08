<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Payment\Enums\DocumentKind;
use App\Domain\Payment\Models\DocumentSequence;
use App\Domain\Shared\Tenancy\CurrentClub;
use LogicException;

/**
 * Allocates the next receipt or refund number for the current club. Runs inside the
 * caller's transaction: the sequence row is locked so concurrent callers serialize.
 * Numbers are per club and never reused.
 */
class NextDocumentNumberAction
{
    public function __construct(private readonly CurrentClub $currentClub) {}

    public function execute(DocumentKind $kind): int
    {
        $clubId = $this->currentClub->id();

        if ($clubId === null) {
            throw new LogicException('Cannot allocate a document number without a current club.');
        }

        $now = now();

        // Insert-or-ignore makes the first allocation idempotent under concurrency:
        // simultaneous callers race on the (club_id, kind) unique key, one row wins.
        DocumentSequence::query()->insertOrIgnore([
            'club_id' => $clubId,
            'kind' => $kind->value,
            'last_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = DocumentSequence::query()
            ->where('kind', $kind->value)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->last_number += 1;
        $sequence->save();

        return $sequence->last_number;
    }
}
