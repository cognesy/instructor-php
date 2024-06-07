<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Template;
use DateTimeImmutable;
use Exception;
use Ramsey\Uuid\Uuid;

class Example
{
    public readonly string $uid;
    public readonly DateTimeImmutable $createdAt;
    private array $output;

    public string $template = <<<TEMPLATE
        INPUT:
        {input}
        
        OUTPUT:
        ```json
        {output}
        ```
        TEMPLATE;

    public function __construct(
        private string $input,
        array|object $output,
        string $template = '',
        string $uid = null,
        DateTimeImmutable $createdAt = null,
    ) {
        $this->uid = $uid ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->template = $template ?: $this->template;
        $this->output = is_array($output) ? $output : Json::parse($output);
    }

    static public function fromChat(array $messages, array|object $output) : self {
        $input = '';
        foreach ($messages as $message) {
            $input .= "{$message['role']}: {$message['content']}\n";
        }
        return new self($input, $output);
    }

    static public function fromText(string $text, array|object $output) : self {
        return new self($text, $output);
    }

    static public function fromData(mixed $data, array|object $output) : self {
        return new self(Json::encode($data), $output);
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

    public function input() : string {
        return $this->input;
    }

    public function output() : array {
        return $this->output;
    }

    public function outputString() : string {
        return Json::encode($this->output);
    }

    public function toString() : string {
        return Template::render($this->template, [
            'input' => $this->input(),
            'output' => $this->outputString(),
        ]);
    }

    public function toMessages() : array {
        return [
            ['role' => 'user', 'content' => $this->input()],
            ['role' => 'assistant', 'content' => $this->outputString()],
        ];
    }

    public function toJson() : string {
        return Json::encode([
            'id' => $this->uid,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'input' => $this->input(),
            'output' => $this->output(),
        ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    }
}
