<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Operators;

use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Utils\Metadata;

final readonly class ParameterOperator
{
    public function __construct(
        protected MessageStore $store,
    ) {}

    // ACCESSORS ////////////////////////////////////////////////

    public function get() : Metadata {
        return $this->store->parameters;
    }

    // MUTATORS /////////////////////////////////////////////////

    public function withParams(array|Metadata $parameters) : MessageStore {
        $newParameters = match(true) {
            $parameters instanceof Metadata => $parameters,
            default => new Metadata($parameters),
        };
        return new MessageStore(
            sections: $this->store->sections(),
            parameters: $newParameters,
        );
    }

    public function setParameter(string $name, mixed $value) : MessageStore {
        return new MessageStore(
            sections: $this->store->sections(),
            parameters: $this->store->parameters->withKeyValue($name, $value),
        );
    }

    public function unsetParameter(string $name) : MessageStore {
        return new MessageStore(
            sections: $this->store->sections(),
            parameters: $this->store->parameters->withoutKey($name),
        );
    }

    public function mergeParameters(array|Metadata $parameters) : MessageStore {
        return new MessageStore(
            sections: $this->store->sections(),
            parameters: $this->store->parameters->withMergedData(
                $parameters instanceof Metadata ? $parameters->toArray() : $parameters
            ),
        );
    }
}