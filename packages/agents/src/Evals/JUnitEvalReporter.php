<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Override;
use RuntimeException;

final class JUnitEvalReporter implements CanReportAgentEvals
{
    /** @var list<EvalResult> */
    private array $results = [];

    public function __construct(private readonly string $path) {}

    #[Override]
    public function id(): string {
        return 'junit:' . $this->path;
    }

    #[Override]
    public function onRunStarted(int $caseCount): void {
        $this->results = [];
    }

    #[Override]
    public function onEvalCompleted(EvalResult $result): void {
        $this->results[] = $result;
    }

    #[Override]
    public function onRunCompleted(EvalRunResult $result): void {
        $cases = '';
        foreach ($this->results as $eval) {
            $body = match ($eval->verdict()) {
                EvalVerdict::Failed => '<failure message="' . self::escape($eval->error() ?? self::failureReason($eval)) . '"/>',
                EvalVerdict::Scored => $result->strict() ? '<failure message="scored eval failed in strict mode"/>' : '',
                EvalVerdict::Skipped => '<skipped message="' . self::escape($eval->skipReason() ?? 'skipped') . '"/>',
                default => '',
            };
            $cases .= '<testcase name="' . self::escape($eval->id()) . '" time="' . sprintf('%.6f', $eval->duration()) . '">' . $body . '</testcase>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><testsuite tests="' . count($this->results) . '">' . $cases . '</testsuite>';
        self::write($this->path, $xml);
    }

    /**
     * A repeated case that missed its pass rate says so, since `gate failed`
     * would send a CI reader looking for a failed assertion that no single trial
     * necessarily has. A case that ran once keeps the original wording verbatim.
     */
    private static function failureReason(EvalResult $eval): string {
        $repetition = $eval->repetition();
        if ($repetition === null || $repetition->satisfied()) {
            return 'gate failed';
        }
        return sprintf(
            'passed %d/%d trials, needed %d/%d',
            $repetition->passCount(),
            $repetition->trialCount(),
            $repetition->requiredPasses(),
            $repetition->trialCount(),
        );
    }

    private static function escape(string $value): string {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function write(string $path, string $content): void {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }
        if (file_put_contents($path . '.tmp', $content) === false || !rename($path . '.tmp', $path)) {
            throw new RuntimeException("Cannot write {$path}");
        }
    }
}
