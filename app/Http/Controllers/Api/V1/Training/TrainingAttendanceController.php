<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Domain\Training\Actions\ListTrainingAttendanceAction;
use App\Domain\Training\Actions\RecordTrainingAttendanceAction;
use App\Domain\Training\DTOs\RecordAttendanceData;
use App\Domain\Training\Models\TrainingGroup;
use App\Http\Requests\Training\ListTrainingAttendanceRequest;
use App\Http\Requests\Training\RecordAttendanceRequest;
use App\Http\Resources\TrainingAttendanceResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrainingAttendanceController
{
    public function index(ListTrainingAttendanceRequest $request, int $group, ListTrainingAttendanceAction $action): AnonymousResourceCollection
    {
        $model = TrainingGroup::query()->findOrFail($group);

        return TrainingAttendanceResource::collection(
            collect($action->execute($model, $request->string('date')->toString())),
        );
    }

    public function store(
        RecordAttendanceRequest $request,
        int $group,
        RecordTrainingAttendanceAction $record,
        ListTrainingAttendanceAction $list,
    ): AnonymousResourceCollection {
        $user = $request->user();
        abort_if($user === null, 401);

        $model = TrainingGroup::query()->findOrFail($group);
        $date = $request->string('date')->toString();

        $record->execute($model, new RecordAttendanceData(
            recordedBy: $user->id,
            date: $date,
            records: $request->attendanceRecords(),
        ));

        return TrainingAttendanceResource::collection(collect($list->execute($model, $date)));
    }
}
