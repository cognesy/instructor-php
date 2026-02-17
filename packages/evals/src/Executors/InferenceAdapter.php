<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors;

use Closure;
use Cognesy\Events\EventBusResolver;
use Cognesy\Evals\Executors\Data\InferenceSchema;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\PendingInference;

class InferenceAdapter
{
    private ?string $debugPreset = null;
    /** @var Closure(object):void|null */
    private ?Closure $wiretap = null;

    public function withDebugPreset(?string $preset) : self {
        $this->debugPreset = $preset;
        return $this;
    }

    /**
     * @param (callable(object):void)|null $callback
     */
    public function wiretap(?callable $callback) : self {
        if ($callback !== null) {
            $this->wiretap = Closure::fromCallable($callback);
        }
        return $this;
    }

    public function callInferenceFor(
        string          $preset,
        OutputMode      $mode,
        bool            $isStreamed,
        string|array    $messages,
        InferenceSchema $evalSchema,
        int             $maxTokens,
    ) : InferenceResponse {
        $messages = is_array($messages) ? $messages : [['role' => 'user', 'content' => $messages]];
        $options = [
            'max_tokens' => $maxTokens,
            'stream' => $isStreamed
        ];
        $inference = match($mode) {
            OutputMode::Tools => $this->forModeTools($preset, $messages, $evalSchema, $options),
            OutputMode::JsonSchema => $this->forModeJsonSchema($preset, $messages, $evalSchema, $options),
            OutputMode::Json => $this->forModeJson($preset, $messages, $evalSchema, $options),
            OutputMode::MdJson => $this->forModeMdJson($preset, $messages, $evalSchema, $options),
            OutputMode::Text => $this->forModeText($preset, $messages, $options),
            OutputMode::Unrestricted => $this->forModeUnrestricted($preset, $messages, $evalSchema, $options),
        };
        return $inference->response();
    }

    public function forModeTools(string $preset, string|array $messages, InferenceSchema $schema, array $options) : PendingInference {
        $request = new InferenceRequest(
            messages: $messages,
            tools: $schema->tools(),
            toolChoice: $schema->toolChoice(),
            options: $options,
            mode: OutputMode::Tools,
        );
        return $this->runtime($preset)->create($request);
    }

    public function forModeJsonSchema(string $preset, string|array $messages, InferenceSchema $schema, array $options) : PendingInference {
        $messagesArray = is_array($messages) ? $messages : [['role' => 'user', 'content' => $messages]];
        $request = new InferenceRequest(
            messages: array_merge($messagesArray, [
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
            ]),
            responseFormat: $schema->responseFormatJsonSchema(),
            options: $options,
            mode: OutputMode::JsonSchema,
        );
        return $this->runtime($preset)->create($request);
    }

    public function forModeJson(string $preset, string|array $messages, InferenceSchema $schema, array $options) : PendingInference {
        $messagesArray = is_array($messages) ? $messages : [['role' => 'user', 'content' => $messages]];
        $request = new InferenceRequest(
            messages: array_merge($messagesArray, [
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
            ]),
            responseFormat: $schema->responseFormatJson(),
            options: $options,
            mode: OutputMode::Json,
        );
        return $this->runtime($preset)->create($request);
    }

    public function forModeMdJson(string $preset, string|array $messages, InferenceSchema $schema, array $options) : PendingInference {
        $messagesArray = is_array($messages) ? $messages : [['role' => 'user', 'content' => $messages]];
        $request = new InferenceRequest(
            messages: array_merge($messagesArray, [
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
                ['role' => 'user', 'content' => '```json'],
            ]),
            options: $options,
            mode: OutputMode::MdJson,
        );
        return $this->runtime($preset)->create($request);
    }

    public function forModeText(?string $preset, string|array $messages, array $options) : PendingInference {
        $request = new InferenceRequest(
            messages: $messages,
            options: $options,
            mode: OutputMode::Text,
        );
        return $this->runtime($preset ?? 'openai')->create($request);
    }

    public function forModeUnrestricted(string $preset, array $messages, InferenceSchema $schema, array $options) : PendingInference {
        $request = new InferenceRequest(
            messages: array_merge($messages, [
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
                ['role' => 'user', 'content' => '```json'],
            ]),
            tools: $schema->tools(),
            toolChoice: $schema->toolChoice(),
            responseFormat: $schema->responseFormatJson(),
            options: $options,
            mode: OutputMode::Unrestricted,
        );
        return $this->runtime($preset)->create($request);
    }

    private function runtime(string $preset): CanCreateInference {
        $events = EventBusResolver::using(null);
        if ($this->wiretap !== null) {
            $events->wiretap($this->wiretap);
        }

        $httpClient = (new HttpClientBuilder(events: $events))
            ->withDebugPreset($this->debugPreset)
            ->create();

        return InferenceRuntime::using(
            preset: $preset,
            events: $events,
            httpClient: $httpClient,
        );
    }
}
