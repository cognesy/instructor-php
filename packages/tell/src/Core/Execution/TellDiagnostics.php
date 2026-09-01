<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Execution;

use Cognesy\Agents\Discovery\DiscoveryResult;
use Cognesy\Tell\Core\Contract\Agent\CanRecordTellAgentDiagnostics;
use Cognesy\Tell\Data\TellDiagnostic;
use Closure;

/** Request-local diagnostics collected while constructing and running Tell. */
final class TellDiagnostics implements CanRecordTellAgentDiagnostics
{
    /** @var array<string, TellDiagnostic> */
    private array $diagnostics = [];
    /** @var list<Closure(): list<string>> */
    private array $warningSources = [];

    /** @param Closure(): list<string> $warnings */
    #[\Override]
    public function trackWarnings(Closure $warnings): void {
        $this->warningSources[] = $warnings;
    }

    #[\Override]
    public function recordExtensionDiscovery(DiscoveryResult $result): void {
        foreach ($result->errors()->all() as $message) {
            $diagnostic = new TellDiagnostic(
                code: 'extension_discovery_error',
                source: 'composer',
                severity: 'warning',
                message: $message,
            );
            $this->diagnostics[$diagnostic->code . "\0" . $diagnostic->message] = $diagnostic;
        }
    }

    /**
     * Records that a run was torn down before it committed. A run that is
     * abandoned mid-flight is a legitimate thing for a caller to do, but it must
     * not be indistinguishable from one that never happened.
     */
    public function recordAbandonedRun(): void {
        $diagnostic = new TellDiagnostic(
            code: 'run_abandoned',
            source: 'runtime',
            severity: 'warning',
            message: 'Tell run was abandoned before it committed; no durable state was published for it.',
        );
        $this->diagnostics[$diagnostic->code . "\0" . $diagnostic->message] = $diagnostic;
    }

    /** @return list<TellDiagnostic> */
    public function all(): array {
        return array_values($this->diagnostics);
    }

    /** @return list<string> */
    public function warnings(): array {
        $warnings = [];
        foreach ($this->warningSources as $source) {
            array_push($warnings, ...$source());
        }

        return $warnings;
    }
}
