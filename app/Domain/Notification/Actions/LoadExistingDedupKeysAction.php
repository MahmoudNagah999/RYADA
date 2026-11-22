<?php

namespace App\Domain\Notification\Actions;

use App\Domain\Notification\Support\ExistingDedupKeys;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reads the dedup keys a set of recipients already holds, in ONE query.
 *
 * Pure read: it never writes. The caller passes recipient ids it already filtered by `club_id`
 * (`User` has no global scope), so a key held by a user of another club can never be seen here.
 * The JSON filter is the one Laravel documents for both MySQL and SQLite: `data->dedup_key`.
 */
class LoadExistingDedupKeysAction
{
    /**
     * @param  list<int>  $recipientIds
     * @param  list<string>  $dedupKeys
     * @return list<string> fingerprints "{recipientId}|{dedupKey}"
     */
    public function execute(array $recipientIds, array $dedupKeys): array
    {
        if ($recipientIds === [] || $dedupKeys === []) {
            return [];
        }

        $rows = DB::table('notifications')
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereIn('notifiable_id', $recipientIds)
            ->whereIn('data->dedup_key', $dedupKeys)
            ->select('notifiable_id', 'data->dedup_key as dedup_key')
            ->get();

        /** @var list<string> $fingerprints */
        $fingerprints = [];

        foreach ($rows as $row) {
            $fingerprints[] = ExistingDedupKeys::fingerprint(
                (int) data_get($row, 'notifiable_id'),
                (string) data_get($row, 'dedup_key'),
            );
        }

        return $fingerprints;
    }
}
