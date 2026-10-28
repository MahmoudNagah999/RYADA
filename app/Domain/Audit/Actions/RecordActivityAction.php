<?php

namespace App\Domain\Audit\Actions;

use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Audit\Models\Activity;
use App\Domain\Club\Models\Club;
use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RecordActivityAction
{
    public function __construct(private readonly CurrentClub $currentClub) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function execute(
        ActivityEvent $event,
        ?Model $subject = null,
        array $properties = [],
        ?int $clubId = null,
        ?Model $causer = null,
    ): Activity {
        $clubId ??= $subject instanceof Club
            ? (int) $subject->getKey()
            : $this->currentClub->id();

        $causer ??= Auth::guard('admin')->user() ?? Auth::guard('sanctum')->user();

        $logger = activity($event->logName())
            ->event($event->value)
            ->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        /** @var Activity $activity */
        $activity = $logger
            ->tap(function (Activity $activity) use ($clubId): void {
                $activity->club_id = $clubId;
            })
            ->log($event->value);

        return $activity;
    }
}
