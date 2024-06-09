<?php
namespace Cognesy\Instructor\Core\Factories;

use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Script;
use Cognesy\Instructor\Data\Messages\Section;
use Cognesy\Instructor\Data\Request;
use Exception;

class ScriptFactory
{
    static public function fromRequest(Request $request) : Script {
        return (new self)->makeScript(
            $request->messages(),
            $request->input(),
            $request->dataAckPrompt(),
            $request->prompt(),
            $request->examples(),
        );
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }

    private function makeScript(
        string|array $messages,
        string|array|object $input,
        string $dataAckPrompt,
        string $prompt,
        array $examples,
    ) : Script {
        if (empty($messages)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }

        $script = new Script();
//        $script->createSection(new Section('system', 'System messages'));
//        $script->createSection(new Section('messages', 'Chat messages'));
//        $script->createSection(new Section('input', 'Data input messages'));
//        $script->createSection(new Section('data_ack', 'Data acknowledged prompt'));
//        $script->createSection(new Section('prompt', 'Command prompt'));
//        $script->createSection(new Section('examples', 'Inference examples'));
//        $script->createSection(new Section('retries', 'Responses and retries'));

        // NORMALIZE MESSAGES
        $messages = $this->normalizeMessages($messages);

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
        $script->section('data_ack')->appendMessage([
            'role' => 'assistant',
            'content' => $dataAckPrompt
        ]);

        // MESSAGES SECTION
        $messagesSection = array_slice($messages, $index);
        if (!empty($messagesSection)) {
            $script->section('messages')->appendMessages($messagesSection);
        }

        // INPUT DATA SECTION
        $inputMessage = Message::fromInput($input);
        if (!$this->inputEmpty($inputMessage)) {
            $script->section('input')->appendMessage($inputMessage);
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
                };
                $script->section('examples')->appendMessages($example->toMessages());
            }
        }
        return $script;
    }

    private function inputEmpty(Message $inputMessage) : bool {
        return $inputMessage->isEmpty() || ($inputMessage->content() === '[]');
    }
}