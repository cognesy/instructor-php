<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class EvalApplication
{
    /** @param callable(string): void|null $stdout @param callable(string): void|null $stderr */
    public function run(array $argv, ?callable $stdout = null, ?callable $stderr = null): int {
        $stdout ??= static fn (string $text) => print($text);
        $stderr ??= static fn (string $text) => fwrite(STDERR, $text);
        try {
            [$root, $options, $list, $json, $junit, $help] = $this->parse($argv);
            if ($help) {
                $stdout($this->usage());
                return EvalExitCode::Success->value;
            }
            $suite = EvalDiscovery::in($root)->discover();
            if ($list) {
                foreach ($suite->filtered($options->filter(), $options->tags(), $options->excludedTags()) as $eval) {
                    $stdout(($eval->id() ?? '') . "\n");
                }
                return EvalExitCode::Success->value;
            }
            $configPath = rtrim($root, '/') . '/evals.config.php';
            $config = is_file($configPath) ? require $configPath : EvalConfig::default();
            if (!$config instanceof EvalConfig) {
                throw new RuntimeException('evals.config.php must return EvalConfig.');
            }
            if ($junit !== null) {
                $config = $config->withReporter(new JUnitEvalReporter($junit));
            }
            $result = (new EvalRunner(config: $config))->run($suite, $options);
            if ($json) {
                $stdout(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
            }
            return $result->exitCode()->value;
        } catch (Throwable $error) {
            $stderr('agents-eval: ' . $error->getMessage() . "\n");
            return EvalExitCode::ConfigurationError->value;
        }
    }

    /** @return array{string, EvalRunOptions, bool, bool, ?string, bool} */
    private function parse(array $argv): array {
        $root = 'evals';
        $filter = null;
        $required = [];
        $excluded = [];
        $strict = false;
        $skip = false;
        $verbose = false;
        $list = false;
        $json = false;
        $junit = null;
        $timeout = null;
        $repeat = 1;
        $passRate = 1.0;
        $help = false;
        foreach (array_slice($argv, 1) as $argument) {
            match (true) {
                $argument === '--help', $argument === '-h' => $help = true,
                $argument === '--strict' => $strict = true,
                $argument === '--skip-report' => $skip = true,
                $argument === '--verbose' => $verbose = true,
                $argument === '--list' => $list = true,
                $argument === '--json' => $json = true,
                str_starts_with($argument, '--filter=') => $filter = substr($argument, 9),
                str_starts_with($argument, '--tag=') => $required[] = substr($argument, 6),
                str_starts_with($argument, '--exclude-tag=') => $excluded[] = substr($argument, 14),
                str_starts_with($argument, '--junit=') => $junit = substr($argument, 8),
                str_starts_with($argument, '--timeout=') => $timeout = $this->parseTimeout(substr($argument, 10)),
                str_starts_with($argument, '--repeat=') => $repeat = $this->parseRepeat(substr($argument, 9)),
                str_starts_with($argument, '--pass-rate=') => $passRate = $this->parsePassRate(substr($argument, 12)),
                str_starts_with($argument, '--') => throw new InvalidArgumentException("Unknown option {$argument}"),
                default => $root = $argument,
            };
        }
        $options = new EvalRunOptions($filter, new EvalTags(...$required), new EvalTags(...$excluded), $strict, $skip, $verbose, $timeout, $repeat, $passRate);
        return [$root, $options, $list, $json, $junit, $help];
    }

    private function usage(): string {
        return <<<'TEXT'
Usage: agents-eval [path] [options]

Options:
  --filter=<glob>        Include IDs matching a glob
  --tag=<tag>            Require a tag; repeatable
  --exclude-tag=<tag>    Exclude a tag; repeatable
  --strict               Fail the run for scored cases
  --timeout=<seconds>    Set a cooperative per-trial budget
  --repeat=<n>           Run every case n times, each with a fresh session
  --pass-rate=<r>        Fraction of trials that must pass, 0 < r <= 1
  --junit=<path>         Write JUnit XML
  --list                 List discovered IDs only
  --verbose              Show passing checks and logs
  --json                 Emit run result JSON
  --skip-report          Disable configured reporters
  -h, --help             Show this help

TEXT;
    }

    private function parseTimeout(string $value): float {
        $timeout = is_numeric($value) ? (float) $value : 0.0;
        if ($timeout <= 0.0) {
            throw new InvalidArgumentException('Timeout must be a positive number of seconds.');
        }
        return $timeout;
    }

    /**
     * Rejects anything that is not a whole number of at least one trial. A
     * non-numeric or fractional value is refused outright rather than cast:
     * `--repeat=abc` would cast to 0 and `--repeat=2.5` to 2, and silently
     * running a different number of trials than asked for would corrupt the
     * measurement the flag exists to produce.
     */
    private function parseRepeat(string $value): int {
        if (preg_match('/^\d+$/', $value) !== 1 || (int) $value < 1) {
            throw new InvalidArgumentException("Repeat must be a whole number of trials of at least 1, got '{$value}'.");
        }
        return (int) $value;
    }

    private function parsePassRate(string $value): float {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Pass rate must be a number greater than 0 and at most 1, got '{$value}'.");
        }
        $passRate = (float) $value;
        if (!is_finite($passRate) || $passRate <= 0.0 || $passRate > 1.0) {
            throw new InvalidArgumentException("Pass rate must be greater than 0 and at most 1, got '{$value}'.");
        }
        return $passRate;
    }
}
