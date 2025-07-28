<?php
namespace Cognesy\Experimental\Module\Core\Traits\Predictor;

use Cognesy\Utils\Arrays;

trait HandlesAccess
{
    public function instructions() : string {
        return match(true) {
            empty($this->instructions) => Arrays::flattenToString([
                $this->signature->getDescription(),
                $this->signature->toSignatureString(),
            ], PHP_EOL),
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

    protected function hasArrayOutput() : bool {
        return $this->signature->hasArrayOutput();
    }

    protected function hasObjectOutput() : bool {
        return $this->signature->hasObjectOutput();
    }

    protected function hasEnumOutput() : bool {
        return $this->signature->hasEnumOutput();
    }

    protected function hasTextOutput() : bool {
        return $this->signature->hasTextOutput();
    }

    protected function hasSingleOutput() : bool {
        return count($this->signature->outputNames()) === 1;
    }
}
