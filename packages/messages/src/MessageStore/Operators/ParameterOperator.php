<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Operators;

use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\MessageStoreParameters;

final readonly class ParameterOperator
{
    public function __construct(
        protected MessageStore $store,
    ) {}

    // ACCESSORS ////////////////////////////////////////////////

    public function get() : MessageStoreParameters {
        return $this->store->parameters;
    }

    // MUTATORS /////////////////////////////////////////////////

    public function withParams(array|MessageStoreParameters $parameters) : MessageStore {
        $newParameters = match(true) {
            $parameters instanceof MessageStoreParameters => $parameters,
            default => new MessageStoreParameters($parameters),
        };
        return new MessageStore(
            sections: $this->store->sections(),
            parameters: $newParameters,
        );
    }

    public function setParameter(string $name, mixed $value) : MessageStore {
        return new MessageStore(
            sections: $this->store->sections(),
            parameters: $this->store->parameters->set($name, $value),
        );
    }

    public function unsetParameter(string $name) : MessageStore {
        return new MessageStore(
            sections: $this->store->sections(),
            parameters: $this->store->parameters->unset($name),
        );
    }

    public function mergeParameters(array|MessageStoreParameters $parameters) : MessageStore {
        return new MessageStore(
            sections: $this->store->sections(),
            parameters: $this->store->parameters->merge($parameters),
        );
    }
}