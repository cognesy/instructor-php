<?php

namespace Cognesy\Instructor\Exceptions;

class DeserializationException extends \Exception
{
    public function __construct(
        public $message,
        public string $modelClass,
        public string $data,
    ) {
        parent::__construct($message);
    }

    public function __toString() : string {
        return json_encode([
            'message' => $this->message,
            'class' => $this->modelClass,
            'data' => $this->data,
        ]);
    }
}
