<?php

declare(strict_types=1);

namespace Cognesy\Tell\Protocol;

use Cognesy\Tell\Runtime\TellExecutionPolicy;
use Cognesy\Tell\TellExecutionMode;
use Cognesy\Tell\TellReasoningEffort;
use Cognesy\Tell\TellRequest;
use JsonException;

final readonly class TellAgentProtocolRequest
{
    public const string SCHEMA = 'tell.agent.request.v1';

    public const int MAX_INPUT_BYTES = 1_048_576;

    public const int MAX_PROMPT_BYTES = 262_144;

    /** @var list<string> */
    private const array FIELDS = [
        'schema',
        'id',
        'prompt',
        'agent',
        'connection',
        'model',
        'reasoningEffort',
        'session',
        'branch',
        'mode',
        'tools',
        'maxSteps',
        'policy',
    ];

    /** @var list<string> */
    private const array POLICY_FIELDS = [
        'maxRetries',
        'timeoutMs',
        'maxOutputChars',
        'maxToolOutputChars',
        'maxToolCalls',
        'maxSpillBytes',
        'maxStubBytes',
    ];

    public function __construct(
        public string $id,
        public TellRequest $request,
    ) {}

    public static function decode(string $input, string $directory): self
    {
        if (strlen($input) > self::MAX_INPUT_BYTES) {
            throw new TellAgentProtocolException('input_limit', 'Request exceeds the maximum input size.');
        }

        $payload = trim($input);
        if ($payload === '') {
            throw new TellAgentProtocolException('invalid_request', 'Request must contain one JSON object.');
        }
        if (str_contains($payload, "\n") || str_contains($payload, "\r")) {
            throw new TellAgentProtocolException('invalid_request', 'Request must be encoded as one JSON line.');
        }
        if (! str_starts_with($payload, '{')) {
            throw new TellAgentProtocolException('invalid_request', 'Request must be a JSON object.');
        }

        try {
            $decoded = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new TellAgentProtocolException('invalid_request', 'Request is not valid JSON.');
        }
        if (! is_array($decoded)) {
            throw new TellAgentProtocolException('invalid_request', 'Request must be a JSON object.');
        }
        if (array_diff(array_keys($decoded), self::FIELDS) !== []) {
            throw new TellAgentProtocolException('invalid_request', 'Request contains unsupported fields.');
        }
        if (($decoded['schema'] ?? null) !== self::SCHEMA) {
            throw new TellAgentProtocolException('unsupported_version', 'Request schema is not supported.');
        }

        $id = self::requiredString($decoded, 'id', 64);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $id) !== 1) {
            throw new TellAgentProtocolException('invalid_request', 'Request id has an invalid format.');
        }
        $prompt = self::requiredString($decoded, 'prompt', self::MAX_PROMPT_BYTES);
        $agent = self::optionalString($decoded, 'agent', 128) ?? 'default';
        $connection = self::optionalString($decoded, 'connection', 128) ?? 'openai';
        $model = self::optionalString($decoded, 'model', 256) ?? '';
        $reasoning = self::reasoningEffort($decoded);
        $session = self::optionalString($decoded, 'session', 128);
        $branch = self::optionalString($decoded, 'branch', 128);
        $mode = self::mode($decoded);
        $tools = self::tools($decoded);
        $maxSteps = self::integer($decoded, 'maxSteps', 1, 100, 10);
        $policy = self::policy($decoded);

        if ($mode === TellExecutionMode::Stateless && ($session !== null || $branch !== null)) {
            throw new TellAgentProtocolException('invalid_request', 'Stateless requests cannot select a session or branch.');
        }
        if ($session !== null && $branch !== null) {
            throw new TellAgentProtocolException('invalid_request', 'A request cannot select both a session and a branch.');
        }

        return new self(
            id: $id,
            request: new TellRequest(
                prompt: $prompt,
                directory: $directory,
                agent: $agent,
                connection: $connection,
                model: $model,
                reasoningEffort: $reasoning,
                session: $session,
                branch: $branch,
                tools: $tools,
                maxSteps: $maxSteps,
                mode: $mode,
                connectionExplicit: array_key_exists('connection', $decoded),
                modelExplicit: array_key_exists('model', $decoded),
                reasoningEffortExplicit: array_key_exists('reasoningEffort', $decoded),
                toolsExplicit: array_key_exists('tools', $decoded),
                policyOverrides: $policy,
                policy: TellExecutionPolicy::resolve([], $policy),
            ),
        );
    }

    /** @param array<string, mixed> $values */
    private static function requiredString(array $values, string $key, int $maxBytes): string
    {
        $value = self::optionalString($values, $key, $maxBytes);
        if ($value === null) {
            throw new TellAgentProtocolException('invalid_request', "Request field {$key} is required.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function optionalString(array $values, string $key, int $maxBytes): ?string
    {
        if (! array_key_exists($key, $values)) {
            return null;
        }
        $value = $values[$key];
        if (! is_string($value) || $value === '' || strlen($value) > $maxBytes || ! mb_check_encoding($value, 'UTF-8')) {
            throw new TellAgentProtocolException('invalid_request', "Request field {$key} must be a bounded non-empty UTF-8 string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function reasoningEffort(array $values): ?TellReasoningEffort
    {
        if (! array_key_exists('reasoningEffort', $values)) {
            return null;
        }
        if (! is_string($values['reasoningEffort'])) {
            throw new TellAgentProtocolException('invalid_request', 'Request field reasoningEffort must be low, medium, or high.');
        }

        return match ($values['reasoningEffort']) {
            'low' => TellReasoningEffort::Low,
            'medium' => TellReasoningEffort::Medium,
            'high' => TellReasoningEffort::High,
            default => throw new TellAgentProtocolException('invalid_request', 'Request field reasoningEffort must be low, medium, or high.'),
        };
    }

    /** @param array<string, mixed> $values */
    private static function mode(array $values): TellExecutionMode
    {
        if (! array_key_exists('mode', $values)) {
            return TellExecutionMode::Stateless;
        }

        return match ($values['mode']) {
            'stateless' => TellExecutionMode::Stateless,
            'durable' => TellExecutionMode::Durable,
            'transient' => TellExecutionMode::Transient,
            default => throw new TellAgentProtocolException('invalid_request', 'Request field mode must be stateless, durable, or transient.'),
        };
    }

    /** @param array<string, mixed> $values
     * @return list<string>
     */
    private static function tools(array $values): array
    {
        if (! array_key_exists('tools', $values)) {
            return [];
        }
        $tools = $values['tools'];
        if (! is_array($tools) || ! array_is_list($tools) || count($tools) > 50) {
            throw new TellAgentProtocolException('invalid_request', 'Request field tools must be a list of at most 50 tool names.');
        }
        foreach ($tools as $tool) {
            if (! is_string($tool) || $tool === '' || strlen($tool) > 128 || ! mb_check_encoding($tool, 'UTF-8')) {
                throw new TellAgentProtocolException('invalid_request', 'Every requested tool must be a bounded non-empty UTF-8 string.');
            }
        }

        return array_values(array_unique($tools));
    }

    /** @param array<string, mixed> $values
     * @return array<string, int>
     */
    private static function policy(array $values): array
    {
        if (! array_key_exists('policy', $values)) {
            return [];
        }
        $policy = $values['policy'];
        if (! is_array($policy) || array_is_list($policy)) {
            throw new TellAgentProtocolException('invalid_request', 'Request field policy must be an object.');
        }
        if (array_diff(array_keys($policy), self::POLICY_FIELDS) !== []) {
            throw new TellAgentProtocolException('invalid_request', 'Request policy contains unsupported fields.');
        }
        $result = [];
        foreach ($policy as $key => $value) {
            if (! is_int($value)) {
                throw new TellAgentProtocolException('invalid_request', 'Every policy value must be an integer.');
            }
            $result[$key] = $value;
        }
        try {
            TellExecutionPolicy::resolve([], $result);
        } catch (\InvalidArgumentException) {
            throw new TellAgentProtocolException('invalid_request', 'One or more policy values are outside the supported range.');
        }

        return $result;
    }

    /** @param array<string, mixed> $values */
    private static function integer(
        array $values,
        string $key,
        int $minimum,
        int $maximum,
        int $default,
    ): int {
        if (! array_key_exists($key, $values)) {
            return $default;
        }
        $value = $values[$key];
        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            throw new TellAgentProtocolException('invalid_request', "Request field {$key} is outside the supported range.");
        }

        return $value;
    }
}
