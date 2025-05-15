<?php

namespace Cognesy\Instructor\Data\Traits\ChatTemplate;

use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Exception;

trait HandlesSections
{
    protected function makeSystem(array $messages, string $system) : Messages {
        $output = new Messages();

        if (!empty($system)) {
            $output->appendMessage(['role' => 'system', 'content' => $system]);
        }

        // EXTRACT SYSTEM ROLE FROM MESSAGES - until first non-system message
        foreach ($messages as $message) {
            if ($message['role'] !== 'system') {
                break;
            }
            $output->appendMessage($message);
        }

        return $output;
    }

    protected function makeMessages(string|array $messages) : Messages {
        $output = new Messages();
        if (empty($messages)) {
            return $output;
        }

        // skip system messages
        $index = 0;
        foreach ($messages as $message) {
            if ($message['role'] !== 'system') {
                break;
            }
            $index++;
        }
        $output->appendMessages(array_slice($messages, $index));
        return $output;
    }

    protected function makeExamples(array $examples) : Messages {
        $messages = new Messages();
        if (empty($examples)) {
            return $messages;
        }
        foreach ($examples as $item) {
            $example = match(true) {
                is_array($item) => Example::fromArray($item),
                is_string($item) => Example::fromJson($item),
                $item instanceof Example => $item,
                default => throw new Exception('Invalid example type'),
            };
            $messages->appendMessages($example->toMessages());
        }
        return $messages;
    }

    protected function makePrompt(string $prompt) : Message {
        return new Message(
            role: 'user',
            content: $prompt
        );
    }

    protected function makeInput(array|object|string $input) : Messages {
        if (empty($input)) {
            return new Messages();
        }
        return Messages::fromInput($input);
    }

    protected function makeJsonSchema(?ResponseModel $responseModel) : array {
        return $responseModel?->toJsonSchema() ?? [];
    }
}