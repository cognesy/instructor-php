<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors;

use Closure;
use Cognesy\Http\Config\DebugConfig;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Evals\Executors\Data\InferenceSchema;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\PendingInference;

class InferenceAdapter
{
    private ?DebugConfig $debugConfig = null;
    /** @var Closure(object):void|null */
    private ?Closure $wiretap = null;

    public function withDebugConfig(DebugConfig $debugConfig) : self {
        $this->debugConfig = $debugConfig;
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
        LLMConfig       $llmConfig,
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
            OutputMode::Tools => $this->forModeTools($llmConfig, $messages, $evalSchema, $options),
            OutputMode::JsonSchema => $this->forModeJsonSchema($llmConfig, $messages, $evalSchema, $options),
            OutputMode::Json => $this->forModeJson($llmConfig, $messages, $evalSchema, $options),
            OutputMode::MdJson => $this->forModeMdJson($llmConfig, $messages, $evalSchema, $options),
            OutputMode::Text => $this->forModeText($llmConfig, $messages, $options),
            OutputMode::Unrestricted => $this->forModeUnrestricted($llmConfig, $messages, $evalSchema, $options),
        };
        return $inference->response();
    }

    public function forModeTools(LLMConfig $llmConfig, string|array $messages, InferenceSchema $schema, array $options) : PendingInference {
        $request = new InferenceRequest(
            messages: Messages::fromAny($messages),
            tools: \Cognesy\Polyglot\Inference\Data\ToolDefinitions::fromArray($schema->tools()),
            toolChoice: \Cognesy\Polyglot\Inference\Data\ToolChoice::fromAny($schema->toolChoice()),
            options: $options,
        );
        return $this->runtime($llmConfig)->create($request);
    }

    public function forModeJsonSchema(LLMConfig $llmConfig, string|array $messages, InferenceSchema $schema, array $options) : PendingInference {
        $messagesArray = is_array($messages) ? $messages : [['role' => 'user', 'content' => $messages]];
        $request = new InferenceRequest(
            messages: Messages::fromArray(array_merge($messagesArray, [
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
            ])),
            responseFormat: \Cognesy\Polyglot\Inference\Data\ResponseFormat::fromArray($schema->responseFormatJsonSchema()),
            options: $options,
        );
        return $this->runtime($llmConfig)->create($request);
    }

    public function forModeJson(LLMConfig $llmConfig, string|array $messages, InferenceSchema $schema, array $options) : PendingInference {
        $messagesArray = is_array($messages) ? $messages : [['role' => 'user', 'content' => $messages]];
        $request = new InferenceRequest(
            messages: Messages::fromArray(array_merge($messagesArray, [
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
            ])),
            responseFormat: \Cognesy\Polyglot\Inference\Data\ResponseFormat::fromArray($schema->responseFormatJson()),
            options: $options,
        );
        return $this->runtime($llmConfig)->create($request);
    }

    public function forModeMdJson(LLMConfig $llmConfig, string|array $messages, InferenceSchema $schema, array $options) : PendingInference {
        $messagesArray = is_array($messages) ? $messages : [['role' => 'user', 'content' => $messages]];
        $request = new InferenceRequest(
            messages: Messages::fromArray(array_merge($messagesArray, [
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
                ['role' => 'user', 'content' => '```json'],
            ])),
            options: $options,
        );
        return $this->runtime($llmConfig)->create($request);
    }

    public function forModeText(LLMConfig $llmConfig, string|array $messages, array $options) : PendingInference {
        $request = new InferenceRequest(
            messages: Messages::fromAny($messages),
            options: $options,
        );
        return $this->runtime($llmConfig)->create($request);
    }

    public function forModeUnrestricted(LLMConfig $llmConfig, array $messages, InferenceSchema $schema, array $options) : PendingInference {
        $request = new InferenceRequest(
            messages: Messages::fromArray(array_merge($messages, [
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema->schema())],
                ['role' => 'user', 'content' => 'Respond correctly with strict JSON.'],
                ['role' => 'user', 'content' => '```json'],
            ])),
            tools: \Cognesy\Polyglot\Inference\Data\ToolDefinitions::fromArray($schema->tools()),
            toolChoice: \Cognesy\Polyglot\Inference\Data\ToolChoice::fromAny($schema->toolChoice()),
            responseFormat: \Cognesy\Polyglot\Inference\Data\ResponseFormat::fromArray($schema->responseFormatJson()),
            options: $options,
        );
        return $this->runtime($llmConfig)->create($request);
    }

    private function runtime(LLMConfig $config): CanCreateInference {
        $events = new EventDispatcher(name: 'evals.inference.adapter');
        if ($this->wiretap !== null) {
            $events->wiretap($this->wiretap);
        }

        $httpClient = (new HttpClientBuilder(events: $events))
            ->withDebugConfig($this->debugConfig ?? new DebugConfig())
            ->create();

        return InferenceRuntime::fromConfig(
            config: $config,
            events: $events,
            httpClient: $httpClient,
        );
    }
}
