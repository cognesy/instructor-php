<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Example;

use BackedEnum;
use Cognesy\Instructor\Data\Traits;
use Cognesy\Messages\Contracts\CanProvideMessages;
use Cognesy\Messages\Messages;
use Cognesy\Template\Template;
use Cognesy\Utils\Json\Json;
use Exception;
use JsonSerializable;

class Example implements CanProvideMessages, JsonSerializable
{
    private mixed $input;
    private mixed $output;
    private bool $isStructured;
    private string $template;

    private ExampleConfig $config;

    public function __construct(
        mixed $input,
        mixed $output,
        bool $isStructured = true,
        string $template = '',
        ?ExampleConfig $config = null,
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->isStructured = $isStructured;
        $this->template = $template;
        $this->config = $config ?? new ExampleConfig();
    }

    // CONSTRUCTORS /////////////////////////////////////////////////////////////////////

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
        $data = Json::decode($json);
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
        );
    }

    static public function fromData(mixed $input, mixed $output) : self {
        return new self($input, $output);
    }

    // ACCESSORS ////////////////////////////////////////////////////////////////////////

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

    public function template() : string {
        return match(true) {
            !empty($this->template) => $this->template,
            default => match(true) {
                $this->isStructured => $this->config->structuredTemplate,
                default => $this->config->textTemplate,
            },
        };
    }

    // CONVERSION ///////////////////////////////////////////////////////////////////////

    public function toString() : string {
        return Template::arrowpipe()
            ->from($this->template())
            ->with([
                'input' => $this->inputString(),
                'output' => $this->outputString(),
            ])
            ->toText();
    }

    public function toXmlArray() : array {
        return ['example' => [
            'input' => ['_cdata' => $this->inputString()],
            'output' => ['_cdata' => "```json\n" . $this->outputString() . "\n```"],
        ]];
    }

    #[\Override]
    public function toMessages() : Messages {
        return Messages::fromArray([
            ['role' => 'user', 'content' => $this->inputString()],
            ['role' => 'assistant', 'content' => $this->outputString()],
        ]);
    }

    public function toJsonString() : string {
        return Json::encode($this, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    }

    // SERIALIZATION ////////////////////////////////////////////////////////////////////

    #[\Override]
    public function jsonSerialize(): array {
        return [
            'input' => $this->input(),
            'output' => $this->output(),
            'template' => $this->template,
        ];
    }

    public function toArray() : array {
        // TODO: should this use TextRepresentation class?
        return match(true) {
            is_array($this->output) => $this->output,
            is_scalar($this->output) => ['value' => $this->output],
            is_object($this->output) && $this->output instanceof BackedEnum => ['value' => $this->output->value()],
            is_object($this->output) && method_exists($this->output, 'toArray') => $this->output->toArray(),
            is_object($this->output) && method_exists($this->output, 'toJson') => $this->output->toJson(),
            is_object($this->output) => get_object_vars($this->output),
            default => [],
        };
    }

    public function clone() : self {
        return new static(
            input: clone $this->input,
            output: clone $this->output,
            isStructured: $this->isStructured,
            template: $this->template
        );
    }
}
