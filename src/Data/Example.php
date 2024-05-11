<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Utils\Json;
use DateTimeImmutable;
use Exception;
use Ramsey\Uuid\Uuid;

class Example
{
    public readonly string $exampleId;
    public readonly DateTimeImmutable $createdAt;

    public string $headerTitle = "# Example:\n";
    public string $headerInput = "## Input:\n";
    public string $headerOutput = "## Output:\n";

    public function __construct(
        private string $input,
        private array $output,
    ) {
        $this->exampleId = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
    }

    static public function fromChat(array $messages, array $output) : self {
        $input = '';
        foreach ($messages as $message) {
            $input .= "{$message['role']}: {$message['content']}\n";
        }
        return new self($input, $output);
    }

    static public function fromText(string $text, array $output) : self {
        return new self($text, $output);
    }

    static public function fromData(mixed $data, array $output) : self {
        return new self(Json::encode($data), $output);
    }

    public function input() : string {
        return $this->input;
    }

    public function output() : array {
        return $this->output;
    }

    public function toString() : string {
        return <<<EXAMPLE
            {$this->headerTitle}
            {$this->headerInput}
            ```
            {$this->input()}
            ```
            {$this->headerOutput}
            ```
            {$this->output()}
            ```
            EXAMPLE;
    }

    public function fromJson(string $json) : self {
        $data = Json::parse($json);
        return new self($data['input'], $data['output']);
    }

    public function toJson() : string {
        return Json::encode([
            'id' => $this->exampleId,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'input' => $this->input(),
            'output' => $this->output(),
        ], JSON_PRETTY_PRINT);
    }

    public function appendTo(array $messages) : array {
        if (empty($messages)) {
            throw new Exception("Cannot append example to empty messages array");
        }
        $count = count($messages);
        $messages[$count-1]['content'] .= "\n" . $this->toString();
        return $messages;
    }
}
