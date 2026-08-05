<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final class EvalLogCollector
{
    private EvalLogs $logs;

    public function __construct() {
        $this->logs = EvalLogs::none();
    }

    /** @param array<string, mixed> $context */
    public function record(string $message, array $context = []): void {
        $this->logs = $this->logs->with(new EvalLog($message, $context));
    }

    public function logs(): EvalLogs {
        return $this->logs;
    }
}
