<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Configuration\TellPaths;

it('uses USERPROFILE when HOME is unavailable', function (): void {
    $profile = 'tell-user-profile';

    tellWithHomeEnvironment(false, $profile, false, static function () use ($profile): void {
        $paths = TellPaths::installed();
        $tellHome = $profile . DIRECTORY_SEPARATOR . '.tell';

        expect($paths->home)->toBe($tellHome)
            ->and($paths->configFile)->toBe($tellHome . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'tell.json')
            ->and($paths->userAgents)->toBe($tellHome . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'agents')
            ->and($paths->sessions)->toBe($tellHome . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'sessions')
            ->and($paths->executionTraces)->toBe($tellHome . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'executions')
            ->and($paths->sessionTraces)->toBe($tellHome . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'sessions');
    });
});

it('prefers HOME over USERPROFILE', function (): void {
    $home = 'tell-home';

    tellWithHomeEnvironment($home, 'tell-user-profile', false, static function () use ($home): void {
        expect(TellPaths::installed()->home)->toBe($home . DIRECTORY_SEPARATOR . '.tell');
    });
});

it('uses TELL_HOME without requiring a platform home', function (): void {
    $configured = 'custom-tell-home';

    tellWithHomeEnvironment(false, false, $configured, static function () use ($configured): void {
        expect(TellPaths::installed()->home)->toBe($configured);
    });
});

it('requires a platform home when TELL_HOME is absent', function (): void {
    tellWithHomeEnvironment(false, false, false, static function (): void {
        expect(static fn (): TellPaths => TellPaths::installed())
            ->toThrow(RuntimeException::class, 'HOME or USERPROFILE must be set');
    });
});

it('resolves an injected environment without reading or mutating the process', function (): void {
    $before = getenv('TELL_HOME');

    $first = TellPaths::installed(['HOME' => '/srv/tenant-a']);
    $second = TellPaths::installed([
        'HOME' => '/srv/tenant-b',
        'TELL_HOME' => '/runtime/tenant-b',
    ]);

    expect($first->home)->toBe('/srv/tenant-a/.tell')
        ->and($second->home)->toBe('/runtime/tenant-b')
        ->and(getenv('TELL_HOME'))->toBe($before);
});

it('keeps a project spill store in Tell storage rather than in the project', function (): void {
    $paths = TellPaths::installed(['TELL_HOME' => '/runtime/tell']);
    $store = $paths->blobsFor('/srv/project-a');

    expect($paths->blobs)->toBe('/runtime/tell' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'blobs')
        ->and($store)->toStartWith($paths->blobs . DIRECTORY_SEPARATOR)
        ->and($store)->not->toStartWith('/srv/project-a')
        // Named for the project path, so two projects never share a store.
        ->and($store)->not->toBe($paths->blobsFor('/srv/project-b'))
        ->and($paths->blobsFor('/srv/project-a'))->toBe($store);
});

/** @param Closure(): void $assertion */
function tellWithHomeEnvironment(
    string|false $home,
    string|false $userProfile,
    string|false $tellHome,
    Closure $assertion,
): void {
    $originalHome = getenv('HOME');
    $originalUserProfile = getenv('USERPROFILE');
    $originalTellHome = getenv('TELL_HOME');
    try {
        tellSetEnvironment('HOME', $home);
        tellSetEnvironment('USERPROFILE', $userProfile);
        tellSetEnvironment('TELL_HOME', $tellHome);
        $assertion();
    } finally {
        tellSetEnvironment('HOME', $originalHome);
        tellSetEnvironment('USERPROFILE', $originalUserProfile);
        tellSetEnvironment('TELL_HOME', $originalTellHome);
    }
}

function tellSetEnvironment(string $name, string|false $value): void {
    if ($value === false) {
        putenv($name);

        return;
    }
    putenv("{$name}={$value}");
}
