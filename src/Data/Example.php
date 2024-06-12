<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Contracts\CanProvideJson;
use Cognesy\Instructor\Contracts\CanProvideMessages;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Template;
use DateTimeImmutable;
use Exception;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

class Example implements CanProvideMessages, CanProvideJson, JsonSerializable
{
    public readonly string $uid;
    public readonly DateTimeImmutable $createdAt;
    private mixed $input;
    private mixed $output;

    public string $template = <<<TEMPLATE
        EXAMPLE INPUT:
        <|input|>
        
        EXAMPLE OUTPUT:
        ```json
        <|output|>
        ```
        TEMPLATE;

    public function __construct(
        mixed $input,
        mixed $output,
        string $template = '',
        string $uid = null,
        DateTimeImmutable $createdAt = null,
    ) {
        $this->uid = $uid ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->template = $template ?: $this->template;
        $this->input = $input;
        $this->output = $output;
    }

    static public function fromChat(array $messages, mixed $output) : self {
        $input = '';
        foreach ($messages as $message) {
            $input .= "{$message['role']}: {$message['content']}\n";
        }
        return new self($input, $output);
    }

    static public function fromText(string $text, mixed $output) : self {
        return new self($text, $output);
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
            uid: $data['id'] ?? Uuid::uuid4(),
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : new DateTimeImmutable(),
        );
    }

    static public function fromData(mixed $data, mixed $output) : self {
        return new self($data, $output);
    }

    public function input() : mixed {
        return $this->input;
    }

    public function output() : mixed {
        return $this->output;
    }

    public function inputString() : string {
        return trim(Messages::fromInput($this->input)->toString());
    }

    public function outputString() : string {
        return trim(Messages::fromInput($this->output)->toString());
    }

    public function toString() : string {
        return Template::render($this->template, [
            'input' => $this->inputString(),
            'output' => $this->outputString(),
        ]);
    }

    public function toMessages() : Messages {
        return Messages::fromArray([
            ['role' => 'user', 'content' => $this->inputString()],
            ['role' => 'assistant', 'content' => $this->outputString()],
        ]);
    }

    public function toJson() : string {
        return Json::encode($this, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->uid,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'input' => $this->input(),
            'output' => $this->output(),
        ];
    }
}
