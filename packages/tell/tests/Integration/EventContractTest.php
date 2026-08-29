<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Console\TellOptions;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Observability\TellEventNormalizer;
use Cognesy\Tell\Runtime\TellSignalCancellationSource;
use Cognesy\Tell\Tell;
use Cognesy\Tell\Tests\Support\TestAutoload;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Execution\TurnException;

it('cooperatively cancels a durable execution before inference and does not publish', function (): void {
    $source = new InMemoryCancellationSource();
    $source->cancel('secret cancellation canary');
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/cancelled-workspace';
    mkdir($project, 0700, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;
    $events = [];
    $normalizer = new TellEventNormalizer(branch: 'main');
    $loop = $factory->build(new TellOptions(prompt: 'Do not infer', directory: $project), cancellation: $source);
    $loop->wiretap(static function (object $event) use (&$events, $normalizer): void {
        $events[] = $normalizer->normalize($event);
    });
    $loop->execute(AgentState::empty()->withUserMessage('Do not infer'));

    expect(fn () => Tell::open($project, $factory, $source)->run(
        TellRequest::prompt('Do not infer')->durable(),
    ))->toThrow(TurnException::class);

    $terminal = array_values(array_filter($events, static fn (array $event): bool => $event['terminal'] !== null));
    expect($terminal)->toHaveCount(1)
        ->and($terminal[0]['terminal'])->toBe('stopped')
        ->and((new FilesystemArena($workspace))->readRef('main')->head)->toBeNull()
        ->and(json_encode($events, JSON_THROW_ON_ERROR))->not->toContain('secret cancellation canary');
});

it('receives SIGINT in a short subprocess when pcntl is available', function (): void {
    if (!TellSignalCancellationSource::isSupported() || !function_exists('posix_kill')) {
        expect(TellSignalCancellationSource::isSupported())->toBeFalse();

        return;
    }
    $script = <<<'PHP'
require $argv[1];
$source = new \Cognesy\Tell\Runtime\TellSignalCancellationSource();
$source->install();
echo "ready\n";
flush();
$state = \Cognesy\Agents\Data\AgentState::empty();
for ($i = 0; $i < 300; $i++) {
    if ($source->cancellationSignal($state) !== null) {
        echo "cancelled\n";
        exit(0);
    }
    usleep(10000);
}
exit(2);
PHP;
    $process = proc_open([PHP_BINARY, '-r', $script, TestAutoload::path()], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start SIGINT test subprocess.');
    }
    expect(trim((string) fgets($pipes[1])))->toBe('ready');
    $status = proc_get_status($process);
    posix_kill($status['pid'], SIGINT);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    expect(proc_close($process))->toBe(0)
        ->and($output)->toContain('cancelled')
        ->and($errors)->toBe('');
});
