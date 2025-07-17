<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\CachedContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class InferenceRequestBuilder
{
    private Messages        $messages;
    private string          $model = '';
    private array           $tools = [];
    private string|array    $toolChoice = [];
    private array           $responseFormat = [];
    private array           $options = [];
    private ?OutputMode     $mode = null;
    protected CachedContext $cachedContext;

    private ?bool            $streaming = null;
    private ?int             $maxTokens = null;

    public function __construct() {
        $this->cachedContext = new CachedContext();
    }

    /**
     * Sets the parameters for the inference request and returns the current instance.
     *
     * @param string|array $messages The input messages for the inference.
     * @param string $model The model to be used for the inference.
     * @param array $tools The tools to be used for the inference.
     * @param string|array $toolChoice The choice of tools for the inference.
     * @param array $responseFormat The format of the response.
     * @param array $options Additional options for the inference.
     * @param OutputMode $mode The mode of operation for the inference.
     */
    public function with(
        string|array|Message|Messages $messages = [],
        string       $model = '',
        array        $tools = [],
        string|array $toolChoice = [],
        array        $responseFormat = [],
        array        $options = [],
        ?OutputMode  $mode = null,
    ) : static {
        $this->messages = Messages::fromAny($messages);
        $this->model = $model;
        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
        $this->responseFormat = $responseFormat;
        $this->options = array_merge($this->options, $options);
        $this->streaming = $options['stream'] ?? $this->streaming;
        $this->mode = $mode;
        return $this;
    }

    public function withMessages(string|array|Message|Messages $messages): static {
        $this->messages = Messages::fromAny($messages);
        return $this;
    }

    public function withModel(string $model): static {
        $this->model = $model;
        return $this;
    }

    public function withTools(array $tools): static {
        $this->tools = $tools;
        return $this;
    }

    public function withToolChoice(string|array $toolChoice): static {
        $this->toolChoice = $toolChoice;
        return $this;
    }

    public function withResponseFormat(array $responseFormat): static {
        $this->responseFormat = $responseFormat;
        return $this;
    }

    public function withOptions(array $options): static {
        $this->options = $options;
        return $this;
    }

    public function withStreaming(bool $stream = true): static {
        $this->streaming = $stream;
        return $this;
    }

    public function withMaxTokens(int $maxTokens): static {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function withOutputMode(?OutputMode $mode): static {
        $this->mode = $mode;
        return $this;
    }

    /**
     * Sets a cached context with provided messages, tools, tool choices, and response format.
     *
     * @param string|array $messages Messages to be cached in the context.
     * @param array $tools Tools to be included in the cached context.
     * @param string|array $toolChoice Tool choices for the cached context.
     * @param array $responseFormat Format for responses in the cached context.
     *
     * @return self
     */
    public function withCachedContext(
        string|array $messages = [],
        array        $tools = [],
        string|array $toolChoice = [],
        array        $responseFormat = [],
    ): self {
        $this->cachedContext = new CachedContext(
            $messages, $tools, $toolChoice, $responseFormat
        );
        return $this;
    }

    public function withRequest(InferenceRequest $request) : self {
        $this->messages = Messages::fromAny($request->messages());
        $this->model = $request->model();
        $this->tools = $request->tools();
        $this->toolChoice = $request->toolChoice();
        $this->responseFormat = $request->responseFormat();
        $this->options = array_merge($this->options, $request->options());
        $this->streaming = $request->isStreamed();
        $this->mode = $request->outputMode();
        $this->cachedContext = $request->cachedContext();

        return $this;
    }

    public function create(): InferenceRequest {
        $options = $this->options;
        $options = $this->override($options, 'stream', $this->streaming);
        $options = $this->override($options, 'max_tokens', $this->maxTokens);

        return new InferenceRequest(
            messages: $this->messages->toArray(),
            model: $this->model,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
            options: $options,
            mode: $this->mode,
            cachedContext: $this->cachedContext
        );
    }

    private function override(array $source, string $key, mixed $value): array {
        if ($value === null) {
            return $source;
        }
        $source[$key] = $value;
        return $source;
    }
}