<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization\Exceptions;

use Cognesy\Utils\Json\Json;

class DeserializationException extends \Exception
{
    public function __construct(
        string $message,
        public string $modelClass,
        public string $data,
    ) {
        parent::__construct($message);
    }

    public function __toString() : string {
        return Json::encode([
            'message' => $this->message,
            'class' => $this->modelClass,
            'data' => $this->data,
        ]);
    }
}
