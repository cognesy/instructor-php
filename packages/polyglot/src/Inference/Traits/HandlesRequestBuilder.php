<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Traits;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

trait HandlesRequestBuilder
{
    private InferenceRequestBuilder $requestBuilder;

    protected function cloneWithRequestBuilder(): static {
        $copy = clone $this;
        $copy->requestBuilder = clone $this->requestBuilder;
        return $copy;
    }

    public function withMessages(string|array|Message|Messages $messages): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withMessages($messages);
        return $copy;
    }

    public function withModel(string $model): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withModel($model);
        return $copy;
    }

    public function withMaxTokens(int $maxTokens): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withMaxTokens($maxTokens);
        return $copy;
    }

    public function withTools(array $tools): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withTools($tools);
        return $copy;
    }

    public function withToolChoice(string|array $toolChoice): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withToolChoice($toolChoice);
        return $copy;
    }

    public function withResponseFormat(array $responseFormat): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withResponseFormat($responseFormat);
        return $copy;
    }

    public function withOptions(array $options): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withOptions($options);
        return $copy;
    }

    public function withStreaming(bool $stream = true): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withStreaming($stream);
        return $copy;
    }

    public function withOutputMode(?OutputMode $mode): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withOutputMode($mode);
        return $copy;
    }

    public function withResponseCachePolicy(ResponseCachePolicy $policy): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withResponseCachePolicy($policy);
        return $copy;
    }

    public function withRetryPolicy(InferenceRetryPolicy $retryPolicy): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withRetryPolicy($retryPolicy);
        return $copy;
    }

    public function withCachedContext(
        string|array $messages = [],
        array        $tools = [],
        string|array $toolChoice = [],
        array        $responseFormat = [],
    ): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withCachedContext($messages, $tools, $toolChoice, $responseFormat);
        return $copy;
    }
}
