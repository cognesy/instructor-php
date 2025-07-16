<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Traits;

use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\InferenceRequestBuilder;

trait HandlesRequestBuilder
{
    private InferenceRequestBuilder $requestBuilder;

    public function withMessages(string|array $messages): static {
        $this->requestBuilder->withMessages($messages);
        return $this;
    }

    public function withModel(string $model): static {
        $this->requestBuilder->withModel($model);
        return $this;
    }

    public function withMaxTokens(int $maxTokens): static {
        $this->requestBuilder->withMaxTokens($maxTokens);
        return $this;
    }

    public function withTools(array $tools): static {
        $this->requestBuilder->withTools($tools);
        return $this;
    }

    public function withToolChoice(string $toolChoice): static {
        $this->requestBuilder->withToolChoice($toolChoice);
        return $this;
    }

    public function withResponseFormat(array $responseFormat): static {
        $this->requestBuilder->withResponseFormat($responseFormat);
        return $this;
    }

    public function withOptions(array $options): static {
        $this->requestBuilder->withOptions($options);
        return $this;
    }

    public function withStreaming(bool $stream = true): static {
        $this->requestBuilder->withStreaming($stream);
        return $this;
    }

    public function withOutputMode(?OutputMode $mode): static {
        $this->requestBuilder->withOutputMode($mode);
        return $this;
    }

    public function withCachedContext(
        string|array $messages = [],
        array        $tools = [],
        string|array $toolChoice = [],
        array        $responseFormat = [],
    ): self {
        $this->requestBuilder->withCachedContext($messages, $tools, $toolChoice, $responseFormat);
        return $this;
    }
}

