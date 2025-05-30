<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Core\StructuredOutputStream;
use Cognesy\Polyglot\LLM\Data\LLMResponse;

trait HandlesShortcuts
{
    public function response() : LLMResponse {
        return $this->create()->response();
    }

    /**
     * Processes a request using provided input, system configurations,
     * and response specifications and returns a streamed result object.
     *
     * @return StructuredOutputStream A streamed version of the response
     */
    public function stream() : StructuredOutputStream {
        $this->withStreaming();
        return $this->create()->stream();
    }

    // get results converted to specific types

    /**
     * Processes a request using provided input, system configurations,
     * and response specifications and returns the result directly.
     *
     * @return mixed A result of processing the request transformed to the target value
     */
    public function get() : mixed {
        return $this->create()->get();
    }

    public function getString() : string {
        return $this->create()->getString();
    }

    public function getFloat() : float {
        return $this->create()->getFloat();
    }

    public function getInt() : int {
        return $this->create()->getInt();
    }

    public function getBoolean() : bool {
        return $this->create()->getBoolean();
    }

    public function getObject() : object {
        return $this->create()->getObject();
    }

    public function getArray() : array {
        return $this->create()->getArray();
    }
}