<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Signature;

trait HandlesMutation
{
    public function setInstructions(string $instructions): void {
        $this->compiled = $instructions;
    }
}