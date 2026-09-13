<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Execution;

use Cognesy\Tell\Core\Contract\Execution\CanExplainTellInfrastructureFailure;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellTermination;
use Cognesy\Tell\Data\TellTraceReference;
use Throwable;

final class TurnException extends WorkspaceException implements CanExplainTellInfrastructureFailure
{
    public function __construct(
        string $message,
        private readonly string $failureCode = 'turn_invariant_violation',
        private readonly ?TellTermination $termination = null,
        private readonly ?TellPublication $publication = null,
        private readonly ?TellTraceReference $trace = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function failureCode(): string {
        return $this->failureCode;
    }

    public function termination(): ?TellTermination {
        return $this->termination;
    }

    public function publication(): ?TellPublication {
        return $this->publication;
    }

    public function trace(): ?TellTraceReference {
        return $this->trace;
    }

    public function withTrace(TellTraceReference $trace): self {
        return new self(
            $this->getMessage(),
            failureCode: $this->failureCode,
            termination: $this->termination,
            publication: $this->publication,
            trace: $trace,
            previous: $this,
        );
    }
}
