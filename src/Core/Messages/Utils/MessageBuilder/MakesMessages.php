<?php

namespace Cognesy\Instructor\Core\Messages\Utils\MessageBuilder;

use Cognesy\Instructor\Core\Messages\Script;
use Cognesy\Instructor\Core\Messages\Section;
use Cognesy\Instructor\Data\Example;
use Exception;

trait MakesMessages
{
    private function makeMessages() : Script {
        if (empty($this->messages)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }

        $script = new Script();
        $script->addSection(new Section('system', 'System messages'));
        $script->addSection(new Section('messages', 'Chat messages'));
        $script->addSection(new Section('command', 'Command prompt'));
        $script->addSection(new Section('data_ack', 'Data acknowledged prompt'));

        // SYSTEM SECTION
        $index = 0;
        foreach ($this->messages as $message) {
            if ($message['role'] !== 'system') {
                break;
            }
            $script->section('system')->add(['role' => 'system', 'content' => $message['content']]);
            $index++;
        }

        // DATA ACK SECTION
        $script->section('data_ack')->add([
            'role' => 'assistant',
            'content' => $this->dataAcknowledgedPrompt
        ]);

        // MESSAGES SECTION
        $script->section('messages')->appendMessages(array_slice($this->messages, $index));

        // PROMPT SECTION
        if (!empty($this->prompt)) {
            $script->section('command')->add([
                'role' => 'user',
                'content' => $this->prompt
            ]);
        }

        // EXAMPLES SECTION
        if (!empty($this->examples)) {
            $script->addSection(new Section('examples', 'Inference examples'));
            foreach ($this->examples as $item) {
                $example = match(true) {
                    is_array($item) => Example::fromArray($item),
                    is_string($item) => Example::fromJson($item),
                    $item instanceof Example => $item,
                };
                $script->section('examples')->appendMessages($example->toMessages());
            }
        }

        return $script;
    }
}
