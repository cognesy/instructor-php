<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\MessageStore;

use Cognesy\Messages\MessageStore\MessageStoreParameters;

trait HandlesParameters
{
    public function parameters() : MessageStoreParameters {
        return $this->parameters;
    }

    public function withParams(array|MessageStoreParameters $parameters) : static {
        $newParameters = match(true) {
            $parameters instanceof MessageStoreParameters => $parameters,
            default => new MessageStoreParameters($parameters),
        };
        return new static(
            sections: $this->sections,
            parameters: $newParameters,
        );
    }

    public function setParameter(string $name, mixed $value) : static {
        return new static(
            sections: $this->sections,
            parameters: $this->parameters->set($name, $value),
        );
    }

    public function unsetParameter(string $name) : static {
        return new static(
            sections: $this->sections,
            parameters: $this->parameters->unset($name),
        );
    }

    public function mergeParameters(array|MessageStoreParameters $parameters) : static {
        return new static(
            sections: $this->sections,
            parameters: $this->parameters->merge($parameters),
        );
    }
}
