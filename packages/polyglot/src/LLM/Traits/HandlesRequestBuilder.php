<?php

namespace Cognesy\Polyglot\LLM\Traits;

use Cognesy\Polyglot\LLM\Data\CachedContext;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

trait HandlesRequestBuilder
{
    private string|array   $messages = [];
    private string         $model = '';
    private array          $tools = [];
    private string|array   $toolChoice = [];
    private array          $responseFormat = [];
    private array          $options = [];
    private ?bool          $streaming = null;
    private ?OutputMode    $mode = null;
    protected CachedContext $cachedContext;

    public function withMessages(string|array $messages): static {
        $this->messages = $messages;
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

    public function withToolChoice(string $toolChoice): static {
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


    public function withOutputMode(OutputMode $mode): static {
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
}

