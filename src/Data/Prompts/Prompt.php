<?php

namespace Cognesy\Instructor\Data\Prompts;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Utils\TemplateUtil;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Not used - may be removed in the future.')]
class Prompt {
    private array $parameters;
    private array $template;

    public function __construct(
        string|array $template = '',
        array $parameters = [],
    ) {
        $this->template = is_string($template) ? [$template] : $template;
        $this->parameters = $parameters;
    }

    public static function render(string|array $template = '', array $parameters = []) : string {
        return (new self($template, $parameters))->toRendered();
    }

    public static function fromJson(string $json) : self {
        $data = json_decode($json, true);
        return new self(
            template: $data['template'],
        );
    }

    public static function fromMessages(Messages $messages) : self {
        $list = [];
        foreach ($messages->all() as $message) {
            $list[] = $message->content();
        }
        return new self(
            template: $list,
        );
    }

    public function withParameters(array $parameters) : static {
        $this->parameters = $parameters;
        return $this;
    }

    public function toRendered() : string {
        return TemplateUtil::render(
            template: $this->templateAsString() ?: $this->autoTemplate(),
            parameters: $this->parameters
        );
    }

    public function toString() : string {
        return match(true) {
            !empty($this->template) => $this->templateAsString(),
            default => $this->autoTemplate(),
        };
    }

    public function toArray(string $role = 'user') : array {
        return [
            'role' => $role,
            'content' => $this->toString(),
        ];
    }

    public function toMessage(string $role = 'user') : Message {
        return Message::fromArray([$role => $this->toString()]);
    }

    protected function templateAsString() : string {
        return implode("\n", $this->template);
    }

    protected function autoTemplate() : string {
        if (empty($this->parameters)) {
            return '';
        }
        $lines = [];
        foreach ($this->parameters as $key => $value) {
            $lines[] = strtoupper($key) . ":";
            $lines[] = "<|$key|>";
            $lines[] = "";
        }
        return implode("\n", $lines);
    }
}
