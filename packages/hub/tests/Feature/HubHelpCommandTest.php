<?php declare(strict_types=1);

use Cognesy\InstructorHub\Commands\HubHelpCommand;
use Cognesy\InstructorHub\Hub;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Symfony's Application::get() calls setCommand() on whatever is registered as
 * `help` before rendering `<command> --help`. Hub overrides that command, so
 * these tests pin the contract it has to keep honouring.
 */
function hubTester(): ApplicationTester {
    $hub = new Hub();
    $hub->setAutoExit(false);
    $hub->setCatchExceptions(false);

    return new ApplicationTester($hub);
}

it('satisfies the help command contract Symfony invokes', function (): void {
    expect(new HubHelpCommand())->toBeInstanceOf(HelpCommand::class)
        ->and(method_exists(HubHelpCommand::class, 'setCommand'))->toBeTrue();
});

it('renders per-command help for --help after a command name', function (string $command, string $usage): void {
    $tester = hubTester();

    $status = $tester->run(['command' => $command, '--help' => true]);
    $display = $tester->getDisplay();

    expect($status)->toBe(0)
        ->and($display)->toContain('Usage:')
        ->toContain($usage)
        ->not->toContain('QUICK START:');
})->with([
    'run' => ['run', 'run [options] [--] <example>'],
    'list' => ['list', 'list [options]'],
    'status' => ['status', 'status [options]'],
]);

it('renders per-command help for the help command with an argument', function (): void {
    $tester = hubTester();

    $status = $tester->run(['command' => 'help', 'command_name' => 'run']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())
        ->toContain('run [options] [--] <example>')
        ->toContain('Example name or index to run');
});

it('keeps the custom Hub overview when no command is addressed', function (array $input): void {
    $tester = hubTester();

    // showHubHelp() writes straight to STDOUT rather than the output object,
    // so the overview has to be captured from the output buffer.
    ob_start();
    $status = $tester->run($input);
    $display = ob_get_clean();

    expect($status)->toBe(0)
        ->and($display)->toContain('Hub - Example Execution & Tracking')
        ->toContain('QUICK START:')
        ->toContain('CORE COMMANDS:');
})->with([
    'bare help' => [['command' => 'help']],
    'help help' => [['command' => 'help', 'command_name' => 'help']],
]);

it('describes every registered command without leaking the overview', function (): void {
    $names = array_diff(array_keys((new Hub())->all()), ['help', 'completion', '_complete']);

    expect($names)->toContain('run', 'list', 'all', 'raw', 'show', 'status', 'stats', 'errors', 'stale', 'clean');

    foreach ($names as $name) {
        $tester = hubTester();
        $status = $tester->run(['command' => $name, '--help' => true]);

        expect($status)->toBe(0)
            ->and($tester->getDisplay())
            ->toContain('Usage:')
            ->toContain($name)
            ->not->toContain('QUICK START:');
    }
});
