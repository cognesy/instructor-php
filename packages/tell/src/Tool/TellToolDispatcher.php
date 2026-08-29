<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tool;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Tell\Configuration\TellExecutionPolicy;
use Cognesy\Tell\Console\TellOptions;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellRuntime;
use InvalidArgumentException;
use JsonException;

/** Invokes one resolved Tell tool without constructing or executing an agent turn. */
final readonly class TellToolDispatcher
{
    public function __construct(
        private TellAgentFactory $agents,
        private ?CanProvideCancellationSignal $cancellation = null,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function dispatch(TellOptions $options, string $name, array $arguments): array {
        if ($this->isCancelled()) {
            return $this->failure($name, 'cancelled', 'Tool invocation was cancelled before execution.');
        }
        $options = (new TellRuntime($this->agents))->resolveDirectOptions($options);
        $policy = $options->policy ?? TellExecutionPolicy::defaults();
        if ($policy->maxToolCalls === 0) {
            return $this->failure($name, 'policy_rejected', 'Tool calls are disabled by the effective execution policy.');
        }

        $loop = $this->agents->build($options);
        if (!$loop->tools()->has($name)) {
            return $this->failure($name, 'tool_unavailable', "Tool '{$name}' is not enabled for this invocation.");
        }

        $tool = $loop->tools()->get($name);
        $this->validateArguments($tool, $arguments);
        $result = $tool->use(...$arguments);
        if ($this->isCancelled()) {
            return $this->failure($name, 'cancelled', 'Tool invocation was cancelled.');
        }
        if ($result->isFailure()) {
            return $this->failure($name, 'runtime_exception', 'Tool invocation failed.', $tool);
        }

        $value = $result->unwrap();
        $payload = is_array($value) && array_key_exists('success', $value)
            ? $value
            : ['success' => true, 'data' => $value, 'error' => null, 'truncated' => false, 'partial' => false];
        $data = $payload['data'] ?? [];
        [$data, $bounded] = $this->bound($data, $policy->maxToolOutputChars);

        return [
            'tool' => $name,
            'success' => $payload['success'] === true,
            'operation' => $payload['operation'] ?? $name,
            'invokedAs' => $payload['invoked_as'] ?? $name,
            'data' => $data,
            'error' => $payload['error'] ?? null,
            'truncated' => $bounded || ($payload['truncated'] ?? false) === true,
            'partial' => ($payload['partial'] ?? false) === true,
            'durationClass' => 'bounded',
            'effect' => $this->effect($tool),
            'execution' => ['mode' => 'direct', 'inference' => false, 'durable' => false],
        ];
    }

    /** @param array<string, mixed> $arguments */
    private function validateArguments(ToolInterface $tool, array $arguments): void {
        $schema = $tool->toToolSchema()->parameters();
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];
        if (!is_array($properties) || !is_array($required)) {
            throw new InvalidArgumentException('Resolved tool has an invalid parameter schema.');
        }
        foreach ($arguments as $name => $value) {
            if (!is_string($name) || !array_key_exists($name, $properties)) {
                throw new InvalidArgumentException("Unknown argument '{$name}' for tool '{$tool->descriptor()->name()}'.");
            }
            if (!is_array($properties[$name]) || !$this->matches($value, $properties[$name])) {
                throw new InvalidArgumentException("Argument '{$name}' does not match the tool schema.");
            }
        }
        foreach ($required as $name) {
            if (is_string($name) && !array_key_exists($name, $arguments)) {
                throw new InvalidArgumentException("Missing required argument '{$name}' for tool '{$tool->descriptor()->name()}'.");
            }
        }
    }

    /** @param array<string, mixed> $schema */
    private function matches(mixed $value, array $schema): bool {
        return match ($schema['type'] ?? null) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && !array_is_list($value),
            default => true,
        };
    }

    private function effect(ToolInterface $tool): string {
        $effect = $tool->descriptor()->metadata()['effect'] ?? 'unknown';

        return is_string($effect) ? $effect : 'unknown';
    }

    /** @return array{0: mixed, 1: bool} */
    private function bound(mixed $value, int $limit): array {
        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return [['text' => 'Tool returned non-serializable data.'], true];
        }
        if (strlen($encoded) <= $limit) {
            return [$value, false];
        }

        return [[
            'text' => substr($encoded, 0, $limit) . "\n...(truncated by Tell execution policy)...",
        ], true];
    }

    private function failure(string $name, string $code, string $message, ?ToolInterface $tool = null): array {
        return [
            'tool' => $name,
            'success' => false,
            'operation' => $name,
            'invokedAs' => $name,
            'data' => [],
            'error' => ['code' => $code, 'message' => $message],
            'truncated' => false,
            'partial' => false,
            'durationClass' => 'bounded',
            'effect' => $tool === null ? 'unknown' : $this->effect($tool),
            'execution' => ['mode' => 'direct', 'inference' => false, 'durable' => false],
        ];
    }

    private function isCancelled(): bool {
        return $this->cancellation?->cancellationSignal(AgentState::empty()) !== null;
    }
}
