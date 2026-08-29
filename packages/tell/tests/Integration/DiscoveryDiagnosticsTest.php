<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Console\TellCommand;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Tell;
use Symfony\Component\Console\Tester\CommandTester;

it('returns malformed extension discovery as typed SDK diagnostics', function (): void {
    $project = tellTestProject();
    $vendor = tellMalformedComposerVendor();
    $factory = tellTestFactory(
        driver: FakeAgentDriver::fromResponses('sdk diagnostic result'),
        composerVendorDir: $vendor,
    );

    $result = Tell::open($project, $factory)->run(TellRequest::prompt('diagnose extensions'));

    expect(trim($result->text()))->toBe('sdk diagnostic result')
        ->and($result->diagnostics())->toHaveCount(1)
        ->and($result->diagnostics()[0]->code)->toBe('extension_discovery_error')
        ->and($result->diagnostics()[0]->source)->toBe('composer')
        ->and($result->diagnostics()[0]->severity)->toBe('warning')
        ->and($result->diagnostics()[0]->message)->toContain('example/malformed-extension');
});

it('renders malformed extension discovery in structured CLI output', function (): void {
    $project = tellTestProject();
    $factory = tellTestFactory(
        driver: FakeAgentDriver::fromResponses('cli diagnostic result'),
        composerVendorDir: tellMalformedComposerVendor(),
    );
    $tester = new CommandTester(new TellCommand($factory));

    $status = $tester->execute([
        'prompt' => 'diagnose extensions',
        '--dir' => $project,
        '--output' => 'json',
    ], ['capture_stderr_separately' => true]);
    $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and($tester->getErrorOutput())->toBe('')
        ->and($payload['diagnostics'])->toHaveCount(1)
        ->and($payload['diagnostics'][0]['code'])->toBe('extension_discovery_error')
        ->and($payload['diagnostics'][0]['message'])->toContain('example/malformed-extension');
});

it('keeps explicit drivers and path configuration isolated between runtimes', function (): void {
    $project = tellTestProject();
    $environmentBefore = getenv();
    $first = tellTestFactory(driver: FakeAgentDriver::fromResponses('tenant one'));
    $second = tellTestFactory(driver: FakeAgentDriver::fromResponses('tenant two'));

    $firstResult = Tell::open($project, $first)->run(TellRequest::prompt('first'));
    $secondResult = Tell::open($project, $second)->run(TellRequest::prompt('second'));

    expect(trim($firstResult->text()))->toBe('tenant one')
        ->and(trim($secondResult->text()))->toBe('tenant two')
        ->and($first->paths()->home)->not->toBe($second->paths()->home)
        ->and(getenv())->toBe($environmentBefore);
});
