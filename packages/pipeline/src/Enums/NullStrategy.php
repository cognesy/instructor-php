<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Enums;

enum NullStrategy {
    case Allow;  // Allow null values to pass through
    case Skip;   // Ignore null values, do not process them
    case Fail;   // Fail the processing if a null value is encountered

    public function is(NullStrategy $onNull) {
        return $this === $onNull;
    }
}