<?php

namespace Cognesy\Polyglot\LLM\Traits;

use Cognesy\Polyglot\LLM\Enums\OutputMode;

trait HandlesFluentMethods
{
    public function withMessages(string|array $messages): static
    {
        $this->request->withMessages($messages);
        return $this;
    }

    public function withModel(string $model): static
    {
        $this->request->withModel($model);
        return $this;
    }

    public function withTools(array $tools): static
    {
        $this->request->withTools($tools);
        return $this;
    }

    public function withToolChoice(string $toolChoice): static
    {
        $this->request->withToolChoice($toolChoice);
        return $this;
    }

    public function withResponseFormat(array $responseFormat): static
    {
        $this->request->withResponseFormat($responseFormat);
        return $this;
    }

    public function withOptions(array $options): static
    {
        $this->request->withOptions($options);
        return $this;
    }

    public function withStreaming(bool $stream = true): static
    {
        $options = $this->request->options();
        $options['stream'] = $stream;
        $this->request->withOptions($options);
        return $this;
    }


    public function withOutputMode(OutputMode $mode): static
    {
        $this->request->withOutputMode($mode);
        return $this;
    }
}

