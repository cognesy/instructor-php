<?php
namespace Cognesy\Instructor\Data\Traits\Example;

use Cognesy\Instructor\Utils\Json;
use DateTimeImmutable;
use Exception;
use Ramsey\Uuid\Uuid;

trait HandlesCreation
{
    static public function fromChat(array $messages, mixed $output) : self {
        $input = '';
        foreach ($messages as $message) {
            $input .= "{$message['role']}: {$message['content']}\n";
        }
        return new self($input, $output);
    }

    static public function fromText(string $input, mixed $output) : self {
        return new self($input, $output);
    }

    static public function fromJson(string $json) : self {
        $data = Json::parse($json);
        if (!isset($data['input']) || !isset($data['output'])) {
            throw new Exception("Invalid JSON data for example - missing `input` or `output` fields");
        }
        return self::fromArray($data);
    }

    static public function fromArray(array $data) : self {
        if (!isset($data['input']) || !isset($data['output'])) {
            throw new Exception("Invalid JSON data for example - missing `input` or `output` fields");
        }
        return new self(
            input: $data['input'],
            output: $data['output'],
            isStructured: $data['structured'] ?? true,
            uid: $data['id'] ?? Uuid::uuid4(),
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : new DateTimeImmutable(),
        );
    }

    static public function fromData(mixed $input, mixed $output) : self {
        return new self($input, $output);
    }
}