<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Common\Execution\CliBinaryGuard;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Sandbox\Enums\SandboxDriver;

it('requires host binary preflight for host driver', function () {
    $previousPath = getenv('PATH');
    putenv('PATH=/__agent_ctrl_missing__/bin');

    try {
        expect(fn() => CliBinaryGuard::assertAvailableForDriver('codex', AgentType::Codex, SandboxDriver::Host))
            ->toThrow(RuntimeException::class);
    } finally {
        restorePathEnvDriver($previousPath);
    }
});

it('requires host binary preflight for firejail driver', function () {
    $previousPath = getenv('PATH');
    putenv('PATH=/__agent_ctrl_missing__/bin');

    try {
        expect(fn() => CliBinaryGuard::assertAvailableForDriver('codex', AgentType::Codex, SandboxDriver::Firejail))
            ->toThrow(RuntimeException::class);
    } finally {
        restorePathEnvDriver($previousPath);
    }
});

it('requires host binary preflight for bubblewrap driver', function () {
    $previousPath = getenv('PATH');
    putenv('PATH=/__agent_ctrl_missing__/bin');

    try {
        expect(fn() => CliBinaryGuard::assertAvailableForDriver('codex', AgentType::Codex, SandboxDriver::Bubblewrap))
            ->toThrow(RuntimeException::class);
    } finally {
        restorePathEnvDriver($previousPath);
    }
});

it('skips host binary preflight for docker driver', function () {
    $previousPath = getenv('PATH');
    putenv('PATH=/__agent_ctrl_missing__/bin');

    try {
        expect(fn() => CliBinaryGuard::assertAvailableForDriver('codex', AgentType::Codex, SandboxDriver::Docker))
            ->not->toThrow(RuntimeException::class);
    } finally {
        restorePathEnvDriver($previousPath);
    }
});

it('skips host binary preflight for podman driver', function () {
    $previousPath = getenv('PATH');
    putenv('PATH=/__agent_ctrl_missing__/bin');

    try {
        expect(fn() => CliBinaryGuard::assertAvailableForDriver('codex', AgentType::Codex, SandboxDriver::Podman))
            ->not->toThrow(RuntimeException::class);
    } finally {
        restorePathEnvDriver($previousPath);
    }
});

function restorePathEnvDriver(string|false $value): void
{
    if ($value === false) {
        putenv('PATH');
        return;
    }

    putenv('PATH=' . $value);
}
