<?php

declare(strict_types=1);

require dirname(__DIR__, 5).'/vendor/autoload.php';

use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 5);
$copy = sys_get_temp_dir().'/polyglot-batch-removal-'.bin2hex(random_bytes(5));
if (!mkdir($copy, 0700)) {
    throw new RuntimeException('Could not create the independent removal copy.');
}

/** @param list<string> $command
 *  @param array<string, string> $environment
 */
function removalCommand(array $command, string $cwd, int $timeout = 120, array $environment = []): void
{
    $process = new Process($command, $cwd, $environment, timeout: $timeout);
    $process->run();
    if (!$process->isSuccessful()) {
        throw new RuntimeException(implode(' ', $command)." failed:\n".substr($process->getErrorOutput()."\n".$process->getOutput(), -4000));
    }
}

function removalDeleteTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($path);
}

function removalReplace(string $path, string $old, string $new): void
{
    $source = file_get_contents($path);
    if ($source === false || substr_count($source, $old) !== 1) {
        throw new RuntimeException("Removal marker missing or duplicated: {$path}");
    }
    if (file_put_contents($path, str_replace($old, $new, $source)) === false) {
        throw new RuntimeException("Could not edit the independent removal copy: {$path}");
    }
}

function removalTreeHash(string $directory): string
{
    $records = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $relative = substr($file->getPathname(), strlen($directory) + 1);
        $records[$relative] = hash_file('sha256', $file->getPathname());
    }
    ksort($records);
    return hash('sha256', json_encode($records, JSON_THROW_ON_ERROR));
}

try {
    removalCommand([
        'rsync', '-a',
        '--exclude=.git', '--exclude=.beads', '--exclude=builds', '--exclude=vendor',
        '--exclude=node_modules', '--exclude=.env', '--exclude=.env.*',
        '--exclude=.venv', '--exclude=.venv-*', '--exclude=.qmd', '--exclude=.codegraph',
        '--exclude=.instructor-hub', '--exclude=.xqa', '--exclude=tmp', '--exclude=research',
        $root.'/', $copy.'/',
    ], $root, 300);
    removalCommand(['rsync', '-a', $root.'/vendor/', $copy.'/vendor/'], $root, 300);

    foreach ([
        'packages/polyglot/src/BatchInference',
        'packages/polyglot/tests/Unit/BatchInference',
        'packages/polyglot/tests/Feature/BatchInference',
        'packages/polyglot/tests/Regression/BatchInference',
        'packages/polyglot/tests/Integration/BatchInference',
        'packages/polyglot/tests/Fixtures/BatchInference',
        'packages/polyglot/tests/Support/BatchInference',
        'packages/polyglot/docs/batch-inference',
        'examples/B09_BatchInference',
    ] as $owned) {
        removalDeleteTree($copy.'/'.$owned);
    }

    removalReplace($copy.'/packages/polyglot/docs/_meta.yaml', "  - batch-inference\n", '');
    removalReplace(
        $copy.'/packages/polyglot/README.md',
        "An experimental, opt-in `BatchInference` facade submits provider-native\nasynchronous jobs and resumes them from a saved reference. See the\n[batch inference guide](docs/batch-inference/overview.md) for submission,\nstatus, cancellation, results, listing, and provider limitations.\n\n",
        '',
    );
    $cheatsheet = $copy.'/packages/polyglot/CHEATSHEET.md';
    $content = file_get_contents($cheatsheet);
    if ($content === false) {
        throw new RuntimeException('Could not read copied cheatsheet.');
    }
    $start = strpos($content, '## Native Batch Jobs (Experimental)');
    $end = strpos($content, '## Inference Quick Start', $start === false ? 0 : $start);
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException('Could not isolate copied batch cheatsheet section.');
    }
    file_put_contents($cheatsheet, substr($content, 0, $start).substr($content, $end));

    $navigation = $copy.'/docs/mint.json';
    $mint = json_decode((string) file_get_contents($navigation), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($mint)) {
        throw new RuntimeException('Copied Mintlify navigation is invalid.');
    }
    $removed = 0;
    $walk = function (array &$node) use (&$walk, &$removed): void {
        $wasList = array_is_list($node);
        foreach ($node as $key => &$value) {
            if (!is_array($value)) {
                continue;
            }
            if (($value['group'] ?? null) === 'Batch Inference') {
                unset($node[$key]);
                $removed++;
                continue;
            }
            $walk($value);
        }
        unset($value);
        if ($wasList) {
            $node = array_values($node);
        }
    };
    $walk($mint);
    if ($removed !== 1) {
        throw new RuntimeException('Expected exactly one copied batch Mintlify group.');
    }
    file_put_contents($navigation, json_encode($mint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

    removalReplace(
        $copy.'/packages/hub/resources/config/examples-groups.yaml',
        "      - id: batch_inference\n        title: Batch Inference\n        include:\n          - source: root\n            path: B09_BatchInference\n",
        '',
    );
    removalReplace(
        $copy.'/.qa/semgrep/config-model.yml',
        "        - \"/packages/polyglot/src/BatchInference/BatchInference.php\"\n",
        '',
    );
    removalReplace($copy.'/composer.json', "        \"aws/aws-sdk-php\": \"^3.0\",\n", '');
    removalReplace(
        $copy.'/packages/polyglot/composer.json',
        "    \"cognesy/instructor-addons\": \"Provided extra optional capabilities like evals, tool use, etc.\",\n    \"aws/aws-sdk-php\": \"Required only for the optional Amazon Bedrock batch inference transport.\"",
        "    \"cognesy/instructor-addons\": \"Provided extra optional capabilities like evals, tool use, etc.\"",
    );

    removalCommand(['composer', 'dump-autoload', '--no-scripts', '--no-interaction'], $copy, 120);
    $autoloadProbe = <<<'PHP'
require 'vendor/autoload.php';
if (class_exists(Cognesy\Polyglot\BatchInference\BatchInference::class)) { exit(1); }
foreach ([Cognesy\Polyglot\Inference\Inference::class, Cognesy\Polyglot\Embeddings\Embeddings::class, Cognesy\Polyglot\Decision\Decision::class] as $class) {
    if (!class_exists($class) || !str_starts_with((new ReflectionClass($class))->getFileName(), getcwd().'/')) { exit(2); }
}
PHP;
    removalCommand(['php', '-r', $autoloadProbe], $copy);
    removalCommand(['just', 'test-package', 'polyglot'], $copy, 300);
    removalCommand(['composer', 'qa:docs-sites'], $copy, 300, ['DOCS_SOURCE_SHA' => hash('sha1', $copy)]);

    $sourceSearch = new Process(['rg', '-n', 'BatchInference', 'packages/polyglot/src', 'packages/http-client/src', 'docs/mint.json', 'packages/polyglot/docs/_meta.yaml', 'packages/hub/resources/config/examples-groups.yaml'], $copy);
    $sourceSearch->run();
    if ($sourceSearch->getExitCode() !== 1) {
        throw new RuntimeException('Batch references remain in the independent copy: '.$sourceSearch->getOutput());
    }
    foreach (['packages/polyglot/src/Inference', 'packages/polyglot/src/Embeddings', 'packages/polyglot/src/Decision', 'packages/http-client/src'] as $stable) {
        if (removalTreeHash($root.'/'.$stable) !== removalTreeHash($copy.'/'.$stable)) {
            throw new RuntimeException("Stable source fingerprint differs after removal: {$stable}");
        }
    }
    echo json_encode([
        'result' => 'passed',
        'copy' => $copy,
        'autoload' => 'independent',
        'polyglotTests' => 'passed',
        'docsSites' => 'passed',
        'stableSourceHashes' => 'matched',
    ], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['result' => 'failed', 'copy' => $copy, 'error' => $error->getMessage()], JSON_THROW_ON_ERROR)."\n");
    exit(1);
}
