<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use Cognesy\Agents\Exceptions\AgentException;
use Cognesy\Agents\Exceptions\InvalidToolArgumentsException;
use Cognesy\Agents\Exceptions\InvalidToolException;
use Cognesy\Agents\Exceptions\ToolCallBlockedException;
use Cognesy\Agents\Exceptions\ToolExecutionBlockedException;
use Cognesy\Agents\Exceptions\ToolExecutionException;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderAuthenticationException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderInvalidRequestException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderQuotaExceededException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderRateLimitException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderTransientException;
use Throwable;

/** Public-safe identity for one error recorded by an agent execution. */
final readonly class TellExecutionError
{
    public function __construct(
        public string $code,
        public string $category,
        public string $phase,
        public string $message,
        private Throwable $cause,
    ) {}

    public static function fromThrowable(Throwable $error): self {
        $cause = $error->getPrevious() ?? $error;

        return match (true) {
            $cause instanceof ProviderRateLimitException => new self(
                'provider_rate_limited',
                'provider',
                'inference',
                'The model provider rate-limited the request.',
                $error,
            ),
            $cause instanceof ProviderQuotaExceededException => new self(
                'provider_quota_exceeded',
                'provider',
                'inference',
                'The model provider quota was exhausted.',
                $error,
            ),
            $cause instanceof ProviderAuthenticationException => new self(
                'provider_authentication_failed',
                'provider',
                'inference',
                'The model provider rejected authentication.',
                $error,
            ),
            $cause instanceof ProviderInvalidRequestException => new self(
                'provider_request_invalid',
                'provider',
                'inference',
                'The model provider rejected the request.',
                $error,
            ),
            $cause instanceof ProviderTransientException => new self(
                'provider_transient_failure',
                'provider',
                'inference',
                'The model provider failed after transient-error handling.',
                $error,
            ),
            $cause instanceof ProviderException => new self(
                'provider_failure',
                'provider',
                'inference',
                'The model provider failed.',
                $error,
            ),
            $cause instanceof TimeoutException => new self(
                'transport_timeout',
                'transport',
                'inference',
                'The model transport timed out.',
                $error,
            ),
            $cause instanceof NetworkException => new self(
                'transport_network_failure',
                'transport',
                'inference',
                'The model transport failed.',
                $error,
            ),
            $cause instanceof HttpRequestException => new self(
                'transport_http_failure',
                'transport',
                'inference',
                'The model HTTP request failed.',
                $error,
            ),
            $cause instanceof InvalidToolArgumentsException => new self(
                'invalid_tool_arguments',
                'tool',
                'tool_selection',
                'A tool call had invalid arguments.',
                $error,
            ),
            $cause instanceof InvalidToolException => new self(
                'invalid_tool',
                'tool',
                'tool_selection',
                'The agent requested an unavailable tool.',
                $error,
            ),
            $cause instanceof ToolCallBlockedException,
            $cause instanceof ToolExecutionBlockedException => new self(
                'tool_execution_blocked',
                'tool',
                'tool_execution',
                'A tool execution was blocked by policy.',
                $error,
            ),
            $cause instanceof ToolExecutionException => new self(
                'tool_execution_failed',
                'tool',
                'tool_execution',
                'A tool execution failed.',
                $error,
            ),
            $cause instanceof AgentException => new self(
                'agent_execution_failed',
                'agent',
                'execution',
                'The agent execution failed.',
                $error,
            ),
            default => new self(
                'execution_failed',
                'runtime',
                'execution',
                'The execution failed.',
                $error,
            ),
        };
    }

    public function cause(): Throwable {
        return $this->cause;
    }

    /** @return array{code: string, category: string, phase: string, message: string} */
    public function toArray(): array {
        return [
            'code' => $this->code,
            'category' => $this->category,
            'phase' => $this->phase,
            'message' => $this->message,
        ];
    }
}
