<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Script;

use Cognesy\Messages\Messages;
use Exception;

trait HandlesConversion
{
    public function toMessages() : Messages {
        $messages = Messages::empty();
        foreach ($this->sections as $section) {
            foreach($section->messages()->each() as $message) {
                if ($message->isEmpty()) {
                    continue;
                }
                $messages = $messages->appendMessage($message->clone());
            }
        }
        return $messages;
    }

    /**
     * @param array<string> $order
     * @return array<string,string|array>
     */
    public function toArray() : array {
        return $this->toMessages()->toArray();
    }

    /**
     * @param array<string> $order
     * @param string $separator
     * @return string
     */
    public function toString() : string {
        return $this->toMessages()->toString();
//        return match(true) {
//            empty($text) => '',
//            default => $this->renderString(
//                template: $text,
//                parameters: $this->parameters()->toArray()
//            )
//        };
    }

    // INTERNAL ////////////////////////////////////////////////////

    protected function fromTemplate(string $name, ?array $parameters) : Messages {
        if (empty($parameters)) {
            return Messages::empty();
        }

        $source = $parameters[$name] ?? throw new Exception("Parameter does not have value: $name");

        // process parameter
        $values = match(true) {
            is_callable($source) => $source($parameters),
            is_array($source) => Messages::fromArray($source),
            $source instanceof Messages => $source,
            is_string($source) => Messages::fromString($source),
            default => throw new Exception("Invalid template value: $name"),
        };

        // process results of callable parameter
        return match(true) {
            $values instanceof Messages => $values,
            is_array($values) => Messages::fromArray($values),
            is_string($values) => Messages::fromString($values),
            default => throw new Exception("Invalid template value: $name"),
        };
    }
}