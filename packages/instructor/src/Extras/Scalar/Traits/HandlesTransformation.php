<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Scalar\Traits;

use Cognesy\Instructor\Extras\Scalar\ValueType;

trait HandlesTransformation
{
    /**
     * Transform response model into scalar value
     */
    public function transform() : mixed {
        // if enum type - return enum instance
        if (self::isEnum($this->enumType)) {
            return ($this->enumType)::from($this->value);
        }

        // try to match value to supported type
        return match ($this->type) {
            ValueType::STRING => (string) $this->value,
            ValueType::INTEGER => (int) $this->value,
            ValueType::FLOAT => (float) $this->value,
            ValueType::BOOLEAN => (bool) $this->value,
            //default => $this->value,
        };
    }
}
