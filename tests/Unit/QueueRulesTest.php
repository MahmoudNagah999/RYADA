<?php

use App\Domain\Shared\Tenancy\BelongsToClub;
use App\Domain\Shared\Tenancy\Queue\TenantAwareJob;
use App\Mail\StaffInvitationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Symfony\Component\Finder\Finder;

/**
 * @return list<class-string>
 */
function queuedAppClasses(): array
{
    // Unit tests do not boot the framework, so base_path() is not available here.
    $appPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app';
    $classes = [];

    foreach ((new Finder)->files()->in($appPath)->name('*.php') as $file) {
        if (! str_contains($file->getContents(), 'ShouldQueue')) {
            continue;
        }

        $class = 'App\\'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $file->getRelativePathname());

        if (class_exists($class) && is_subclass_of($class, ShouldQueue::class)) {
            $classes[] = $class;
        }
    }

    return $classes;
}

it('finds the queued classes it is supposed to check', function () {
    expect(queuedAppClasses())->toContain(StaffInvitationMail::class);
});

it('requires every queued job and listener to carry the club id', function () {
    // Mailables are exempt: they carry plain values only (checked by the next test).
    $violations = collect(queuedAppClasses())
        ->reject(fn (string $class): bool => is_subclass_of($class, Mailable::class))
        ->reject(fn (string $class): bool => is_subclass_of($class, TenantAwareJob::class))
        ->values()
        ->all();

    expect($violations)->toBe([]);
});

it('never queues a tenant-owned model', function () {
    $violations = [];

    foreach (queuedAppClasses() as $class) {
        foreach ((new ReflectionClass($class))->getProperties() as $property) {
            $type = $property->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $name = $type->getName();

            if (class_exists($name)
                && is_subclass_of($name, Model::class)
                && in_array(BelongsToClub::class, class_uses_recursive($name), true)) {
                $violations[] = "{$class}::\${$property->getName()} ({$name})";
            }
        }
    }

    expect($violations)->toBe([]);
});
