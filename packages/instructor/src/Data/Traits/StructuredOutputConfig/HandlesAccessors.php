<?php

namespace Cognesy\Instructor\Data\Traits\StructuredOutputConfig;

use Cognesy\Polyglot\Inference\Enums\OutputMode;

trait HandlesAccessors
{
    // ACCESSORS ///////////////////////////////////////////////////////

    public function outputMode() : OutputMode {
        return $this->outputMode;
    }

    public function prompt(OutputMode $mode) : string {
        return $this->modePrompts[$mode->value] ?? '';
    }

    public function modePrompts() : array {
        return $this->modePrompts;
    }

    public function retryPrompt() : string {
        return $this->retryPrompt;
    }

    public function chatStructure() : array {
        return $this->chatStructure;
    }

    public function schemaName() : string {
        return $this->schemaName;
    }

    public function schemaDescription() : string {
        return $this->schemaDescription;
    }

    public function toolName() : string {
        return $this->toolName;
    }

    public function toolDescription() : string {
        return $this->toolDescription;
    }

    public function useObjectReferences() : bool {
        return $this->useObjectReferences;
    }

    public function maxRetries() : int {
        return $this->maxRetries;
    }

    public function outputClass() : string {
        return $this->outputClass;
    }

    public function deserializationErrorPrompt() : string {
        return $this->deserializationErrorPrompt;
    }

    public function defaultToStdClass() : bool {
        return $this->defaultToStdClass;
    }

    public function throwOnTransformationFailure() : bool {
        return $this->throwOnTransformationFailure;
    }
}