<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use JsonException;

final class StructuredOutputFakeRuntime implements CanCreateStructuredOutput
{
    /** @var list<StructuredOutputRequest> */
    private array $recorded = [];

    /**
     * @param array<string, mixed> $responses
     */
    public function __construct(
        private readonly array $responses,
        private readonly EventDispatcher $events = new EventDispatcher('symfony-structured-output-fake'),
        private readonly StructuredOutputConfig $config = new StructuredOutputConfig(),
    ) {}

    /**
     * @param array<string, mixed> $responses
     */
    public static function fromResponses(array $responses): self
    {
        return new self($responses);
    }

    /** @return list<StructuredOutputRequest> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function create(StructuredOutputRequest $request): PendingStructuredOutput
    {
        $this->recorded[] = $request;

        $runtime = new StructuredOutputRuntime(
            inference: InferenceFakeRuntime::fromResponses($this->responseFor($request)),
            events: $this->events,
            config: $this->config,
        );

        return $runtime->create($request);
    }

    private function responseFor(StructuredOutputRequest $request): InferenceResponse
    {
        $key = $this->requestedSchemaKey($request);
        $response = $this->responses[$key]
            ?? $this->responses['default']
            ?? throw new \RuntimeException("No fake structured output response defined for [{$key}].");

        return match (true) {
            $response instanceof InferenceResponse => $response,
            default => new InferenceResponse(content: $this->encodeResponse($response)),
        };
    }

    private function requestedSchemaKey(StructuredOutputRequest $request): string
    {
        $schema = $request->requestedSchema();

        return match (true) {
            is_string($schema) => ltrim($schema, '\\'),
            is_object($schema) => $schema::class,
            is_array($schema) => 'array',
            default => 'default',
        };
    }

    private function encodeResponse(mixed $response): string
    {
        try {
            return json_encode($response, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Failed to encode fake structured output response.', 0, $exception);
        }
    }
}
