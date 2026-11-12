<?php

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Actions\RegisterClubAction;
use App\Domain\Club\DTOs\RegisterClubData;
use App\Domain\Notification\Enums\NotificationCode;
use App\Domain\Notification\Notifications\WelcomeClubNotification;
use App\Domain\Staff\Actions\RemoveStaffAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Notification\NotificationFixture;
use Tests\Support\Tenancy\TenantFixture;

uses(RefreshDatabase::class);

it('sends a welcome notification to the new owner on club registration', function () {
    $result = app(RegisterClubAction::class)->execute(new RegisterClubData(
        clubName: 'Talae El Zeitoun',
        ownerName: 'Mahmoud',
        email: 'owner@example.com',
        password: 'password123',
        locale: 'en',
    ));

    $notifications = $result->user->notifications()->get();

    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]->data['code'])->toBe(NotificationCode::ClubWelcome->value)
        ->and($notifications[0]->data['params'])->toEqual(['club_name' => 'Talae El Zeitoun'])
        ->and($notifications[0]->data['dedup_key'])->toBeNull();
});

it('deletes the notifications of a staff member removed from the club', function () {
    $owner = TenantFixture::create(RoleName::Owner);
    $staff = NotificationFixture::userWithRole($owner, RoleName::Staff);

    $staff->notify(new WelcomeClubNotification('Talae El Zeitoun'));

    expect(DB::table('notifications')->where('notifiable_id', $staff->id)->count())->toBe(1);

    app(RemoveStaffAction::class)->execute($owner->user, $staff);

    expect(DB::table('notifications')->where('notifiable_id', $staff->id)->count())->toBe(0);
});
