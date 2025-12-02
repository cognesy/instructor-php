<?php declare(strict_types=1);

// Simple streaming demo for ClaudeCodeCli
// Usage: php packages/auxiliary/src/ClaudeCodeCli/examples/streaming-demo.php "your prompt here"

require __DIR__ . '/../../../../../vendor/autoload.php';

use Cognesy\Auxiliary\ClaudeCodeCli\Application\Builder\ClaudeCommandBuilder;
use Cognesy\Auxiliary\ClaudeCodeCli\Application\Dto\ClaudeRequest;
use Cognesy\Auxiliary\ClaudeCodeCli\Application\Parser\ResponseParser;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\InputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\PermissionMode;
use Cognesy\Auxiliary\ClaudeCodeCli\Infrastructure\Execution\SandboxCommandExecutor;
use Cognesy\Auxiliary\ClaudeCodeCli\Infrastructure\Execution\SandboxDriver;
use Cognesy\Auxiliary\ClaudeCodeCli\Infrastructure\Execution\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy as SandboxPolicy;

$prompt = $argv[1] ?? 'Say hello and describe this project briefly.';
$driver = $argv[2] ?? 'host'; // host|docker|podman|firejail|bubblewrap
$baseDir = realpath(__DIR__ . '/../../../../tmp/bwrap') ?: sys_get_temp_dir();

$request = new ClaudeRequest(
    prompt: $prompt,
    outputFormat: OutputFormat::StreamJson,
    permissionMode: PermissionMode::Plan,
    includePartialMessages: true,
    inputFormat: InputFormat::Text,
    verbose: true, // required by claude for stream-json output
);

$builder = new ClaudeCommandBuilder();
$spec = $builder->buildHeadless($request);

$policy = ExecutionPolicy::custom(
    timeoutSeconds: 120,
    networkEnabled: true,
    stdoutLimitBytes: 5 * 1024 * 1024,
    stderrLimitBytes: 1 * 1024 * 1024,
    baseDir: $baseDir,
    inheritEnv: true,
);

// For podman/docker, ensure rootless storage uses a writable location to avoid permission errors.
if (in_array($driver, ['podman', 'docker'], true)) {
    $storage = $baseDir . '/containers';
    $runroot = $baseDir . '/containers-run';
    if (!is_dir($storage)) {
        @mkdir($storage, 0o700, true);
    }
    if (!is_dir($runroot)) {
        @mkdir($runroot, 0o700, true);
    }
    putenv('CONTAINERS_STORAGE_DIR=' . $storage);
    putenv('XDG_RUNTIME_DIR=' . $runroot);
}

$executor = new SandboxCommandExecutor(
    policy: $policy,
    driver: match ($driver) {
        'docker' => SandboxDriver::Docker,
        'podman' => SandboxDriver::Podman,
        'firejail' => SandboxDriver::Firejail,
        'bubblewrap' => SandboxDriver::Bubblewrap,
        default => SandboxDriver::Host,
    },
);
$execResult = $executor->execute($spec);

$response = (new ResponseParser())->parse($execResult, OutputFormat::StreamJson);

$emitted = false;
foreach ($response->decoded()->all() as $event) {
    $data = $event->data();
    if (isset($data['message']['content'][0]['text'])) {
        echo $data['message']['content'][0]['text'] . PHP_EOL;
        $emitted = true;
        continue;
    }
    if (isset($data['result'])) {
        echo '[result] ' . $data['result'] . PHP_EOL;
        $emitted = true;
        continue;
    }
    if (isset($data['text'])) {
        echo $data['text'] . PHP_EOL;
        $emitted = true;
    }
}

if (!$emitted) {
    echo "[exit {$execResult->exitCode()}]" . PHP_EOL;
    if ($execResult->stdout() !== '') {
        echo $execResult->stdout() . PHP_EOL;
    }
    if ($execResult->stderr() !== '') {
        echo '[stderr] ' . $execResult->stderr() . PHP_EOL;
    }
}
