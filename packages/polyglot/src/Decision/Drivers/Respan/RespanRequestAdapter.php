<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Drivers\Respan;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\DecisionRequestAdapter;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Questions\Noul;
use JsonException;
use Override;
use stdClass;

final readonly class RespanRequestAdapter implements DecisionRequestAdapter
{
    private const array MODELS = ['span-01-free', 'span-01-pro'];

    public function __construct(private DecisionConfig $config) {}

    #[Override]
    public function toHttpClientRequest(DecisionRequest $request): HttpRequest
    {
        $model = $request->model() ?? $this->config->model;
        $this->config->assertRoutingIdentity($model);
        $this->config->assertHttpTarget();
        $this->config->assertBearerAuthentication();
        if (! in_array($model, self::MODELS, true)) {
            throw new DecisionInvalidRequestException("RESPAN does not support model '{$model}'.");
        }

        $body = new stdClass;
        $body->model = $model;
        $body->span = $this->span($request);
        $body->behaviors = $this->behaviors($request);

        try {
            $encoded = json_encode(
                $body,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw new DecisionInvalidRequestException('RESPAN request body cannot be encoded.');
        }

        $httpRequest = new HttpRequest(
            url: rtrim($this->config->apiUrl, '/').'/'.ltrim($this->config->endpoint, '/'),
            method: 'POST',
            headers: [
                'Authorization' => "Bearer {$this->config->apiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            body: $encoded,
            options: [],
        );

        $correlation = $request->telemetryCorrelation();

        return match ($correlation) {
            null => $httpRequest,
            default => HttpRequestTelemetry::withCorrelation($httpRequest, $correlation),
        };
    }

    private function span(DecisionRequest $request): stdClass
    {
        $span = $request->input()->value();
        if (! $span instanceof stdClass) {
            throw new DecisionInvalidRequestException('RESPAN input must be a conversation span object.');
        }
        $this->assertExactFields($span, ['input', 'output'], 'RESPAN conversation span');
        if (! is_array($span->input ?? null)) {
            throw new DecisionInvalidRequestException('RESPAN span input must be a list of messages.');
        }
        foreach ($span->input as $index => $message) {
            $this->assertMessage($message, "RESPAN span input message {$index}");
        }
        $this->assertMessage($span->output ?? null, 'RESPAN span output message');

        return $span;
    }

    /** @return list<stdClass> */
    private function behaviors(DecisionRequest $request): array
    {
        $behaviors = [];
        foreach ($request->questions()->all() as $question) {
            if (! $question instanceof Noul) {
                throw new DecisionInvalidRequestException('RESPAN supports only Noul questions.');
            }
            $definition = $this->definition($question);
            if (mb_strlen($definition) < 3) {
                throw new DecisionInvalidRequestException('RESPAN behavior definitions require at least 3 characters.');
            }
            $behavior = new stdClass;
            $behavior->id = $question->id();
            $behavior->definition = $definition;
            $behaviors[] = $behavior;
        }

        return $behaviors;
    }

    private function definition(Noul $question): string
    {
        $instructions = $question->instructions();
        if ($instructions === null) {
            throw new DecisionInvalidRequestException('RESPAN Noul questions require instructions.');
        }
        $parts = [$this->render($instructions)];
        $true = $question->criteria()?->trueDescription();
        $false = $question->criteria()?->falseDescription();
        if ($true !== null) {
            $parts[] = 'Present when: '.$this->render($true);
        }
        if ($false !== null) {
            $parts[] = 'Absent when: '.$this->render($false);
        }

        return trim(implode("\n", $parts));
    }

    private function render(JsonContent $content): string
    {
        $value = $content->value();
        if (is_string($value)) {
            return trim($value);
        }

        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw new DecisionInvalidRequestException('RESPAN behavior definition cannot be encoded.');
        }
    }

    private function assertMessage(mixed $message, string $context): void
    {
        if (! $message instanceof stdClass) {
            throw new DecisionInvalidRequestException("{$context} must be an object.");
        }
        $this->assertExactFields($message, ['role', 'content'], $context);
        if (property_exists($message, 'role') && (! is_string($message->role) || trim($message->role) === '')) {
            throw new DecisionInvalidRequestException("{$context} role must be a non-empty string when provided.");
        }
        if (! is_string($message->content ?? null) || trim($message->content) === '') {
            throw new DecisionInvalidRequestException("{$context} content must be a non-empty string.");
        }
    }

    /** @param list<string> $expected */
    private function assertExactFields(stdClass $object, array $expected, string $context): void
    {
        $actual = array_keys(get_object_vars($object));
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($actual !== $expected) {
            throw new DecisionInvalidRequestException("{$context} fields are invalid.");
        }
    }
}
