<?php
namespace Cognesy\Instructor\Core\Factories;

use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Script;
use Cognesy\Instructor\Data\Request;
use Exception;

class ScriptFactory
{

    public function fromRequest(Request $request) : Script {
        return (new self)->makeScript($request);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }

    private function makeScript(Request $request) : Script {
        if ($this->isRequestEmpty($request)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }

        $script = new Script();

        // GET DATA
        $messages = $this->normalizeMessages($request->messages());
        $input = $request->input();
        $examples = $request->examples();
        $prompt = $request->prompt();
        $dataAckPrompt = $request->dataAckPrompt();

        // SYSTEM SECTION
        $index = 0;
        foreach ($messages as $message) {
            if ($message['role'] !== 'system') {
                break;
            }
            $script->section('system')->appendMessage(['role' => 'system', 'content' => $message['content']]);
            $index++;
        }

        // DATA ACK SECTION
        //$script->section('data-ack')->appendMessage([
        //    'role' => 'assistant',
        //    'content' => $dataAckPrompt
        //]);

        // MESSAGES SECTION
        $messagesSection = array_slice($messages, $index);
        if (!empty($messagesSection)) {
            $script->section('messages')->appendMessages($messagesSection);
        }

        // INPUT DATA SECTION
        $inputMessages = Messages::fromInput($input);
        if (!$this->isInputEmpty($inputMessages)) {
            $script->section('input')->appendMessages($inputMessages);
        }

        // PROMPT SECTION
        if (!empty($prompt)) {
            $script->section('prompt')->appendMessage([
                'role' => 'user',
                'content' => $prompt
            ]);
        }

        // EXAMPLES SECTION
        if (!empty($examples)) {
            foreach ($examples as $item) {
                $example = match(true) {
                    is_array($item) => Example::fromArray($item),
                    is_string($item) => Example::fromJson($item),
                    $item instanceof Example => $item,
                    default => throw new Exception('Invalid example type'),
                };
                $script->section('examples')->appendMessage(Message::fromString($example->toString()));
            }
        }
        return $script;
    }

    private function isInputEmpty(Message|Messages $inputMessages) : bool {
        return match(true) {
            $inputMessages instanceof Messages => $inputMessages->isEmpty(),
            $inputMessages instanceof Message => $inputMessages->isEmpty() || $inputMessages->isNull(),
            default => true,
        };
    }

    private function isRequestEmpty(Request $request) : bool {
        return match(true) {
            !empty($request->messages()) => false,
            !empty($request->input()) => false,
            !empty($request->examples()) => false,
            !empty($request->prompt()) => false,
            default => true,
        };
    }
}