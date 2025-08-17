<?php declare(strict_types=1);

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;

trait HandlesOverrides
{
    protected array $validators = [];
    protected array $transformers = [];
    protected array $deserializers = [];

    public function withValidators(CanValidateObject|string ...$validators) : static {
        $this->validators = $validators;
        return $this;
    }

    public function withTransformers(CanTransformData|string ...$transformers) : static {
        $this->transformers = $transformers;
        return $this;
    }

    public function withDeserializers(CanDeserializeClass|string ...$deserializers) : static {
        $this->deserializers = $deserializers;
        return $this;
    }
}
