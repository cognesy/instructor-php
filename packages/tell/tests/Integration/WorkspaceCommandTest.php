<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\TellApplication;
use HelgeSverre\Toon\Toon;
use Symfony\Component\Console\Output\BufferedOutput;

it('initializes a workspace through the public command in TOON and JSON', function (): void {
    $root = tellWorkspaceCommandDirectory('init');
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);

    $toonOutput = new BufferedOutput;
    $toonStatus = $application->runArgv(['tell', 'init', $root], $toonOutput);
    $toon = Toon::decode($toonOutput->fetch());

    $jsonOutput = new BufferedOutput;
    $jsonStatus = $application->runArgv(['tell', 'init', $root, '--json'], $jsonOutput);
    $json = json_decode($jsonOutput->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($toonStatus)->toBe(0)
        ->and($toon)->toMatchArray([
            'workspace' => realpath($root),
            'arena' => realpath($root).'/.tell/arena',
            'schema' => 1,
            'status' => 'initialized',
        ])
        ->and($jsonStatus)->toBe(0)
        ->and($json)->toMatchArray([
            'workspace' => realpath($root),
            'arena' => realpath($root).'/.tell/arena',
            'schema' => 1,
            'status' => 'already-initialized',
        ]);
});

it('reports the discovered durable workspace from a nested stateless home view', function (): void {
    $root = tellWorkspaceCommandDirectory('discover');
    $nested = $root.'/deep/path';
    mkdir($nested, 0700, true);
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $initOutput = new BufferedOutput;
    $application->runArgv(['tell', 'init', $root], $initOutput);

    $output = new BufferedOutput;
    $status = $application->runArgv(['tell', '--dir', $nested, '--output=toon'], $output);
    $payload = Toon::decode($output->fetch());

    expect($status)->toBe(0)
        ->and($payload['workspace'])->toMatchArray([
            'root' => realpath($root),
            'arena' => realpath($root).'/.tell/arena',
            'schema' => 1,
        ])
        ->and($payload['help'])->toContain('Run `tell init` to initialize durable project state.');
});

it('keeps a bare stateless invocation outside a workspace read-only and actionable', function (): void {
    $root = tellWorkspaceCommandDirectory('outside');
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $before = scandir($root);

    $output = new BufferedOutput;
    $status = $application->runArgv(['tell', '--dir', $root, '--output=toon'], $output);
    $payload = Toon::decode($output->fetch());

    expect($status)->toBe(0)
        ->and($payload['workspace'])->toBeNull()
        ->and($payload['help'])->toContain('Run `tell init` to initialize durable project state.')
        ->and(scandir($root))->toBe($before);
});

it('keeps init failures structured and non-mutating in JSON mode', function (): void {
    $root = tellWorkspaceCommandDirectory('invalid');
    file_put_contents($root.'/.tell', 'not a directory');
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $before = scandir($root);

    $output = new BufferedOutput;
    $status = $application->runArgv(['tell', 'init', $root, '--json'], $output);
    $payload = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(1)
        ->and($payload['error'])->toContain('workspace marker is not a directory')
        ->and(scandir($root))->toBe($before);
});

it('keeps invalid init paths actionable in JSON mode', function (): void {
    $application = new TellApplication(tellTestFactory());
    $application->setAutoExit(false);
    $missing = sys_get_temp_dir().'/instructor-tell-missing-'.bin2hex(random_bytes(6));

    $output = new BufferedOutput;
    $status = $application->runArgv(['tell', 'init', $missing, '--json'], $output);
    $payload = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(2)
        ->and($payload['error'])->toContain('Workspace directory does not exist')
        ->and($payload['help'])->toBe(['Run `tell init [path]` with an existing project directory.']);
});

function tellWorkspaceCommandDirectory(string $name): string
{
    global $tellTemporaryRoots;

    $root = sys_get_temp_dir().'/instructor-tell-workspace-command-'.$name.'-'.bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    $tellTemporaryRoots[] = $root;

    return $root;
}
