<?php

namespace Cognesy\Instructor\Extras\Module\Core\Traits\Predict;

use Cognesy\Instructor\Utils\Arrays;

trait HandlesAccess
{
    public function instructions() : string {
        return match(true) {
            empty($this->instructions) => Arrays::flatten([
                $this->signature->getDescription(),
                $this->signature->toSignatureString(),
            ]),
            default => $this->instructions,
        };
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    protected function signatureDiff(array $callArgs) : array {
        $expected = $this->signature->inputNames();
        $actual = array_keys($callArgs);
        return array_diff($expected, $actual);
    }

    protected function hasScalarOutput() : bool {
        return $this->signature->hasScalarOutput();
    }

    protected function hasTextOutput() : bool {
        return $this->signature->hasTextOutput();
    }

    protected function hasSingleOutput() : bool {
        return count($this->signature->outputNames()) === 1;
    }
}
