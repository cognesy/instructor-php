<?php
namespace Cognesy\Instructor\Data\Traits\ChatTemplate;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Script;

trait HandlesCachedContext
{
    protected function makeCachedScript(array $cachedContext) : Script {
        if (empty($cachedContext)) {
            return new Script();
        }

        $script = new Script();
        $messages = $this->normalizeMessages($cachedContext['messages']);

        $script->section('system')->prependMessages(
            $this->makeSystem($messages, $cachedContext['system'])
        );
        $script->section('cached-messages')->appendMessages(
            $this->makeMessages($messages)
        );
        $script->section('cached-input')->appendMessages(
            $this->makeInput($cachedContext['input'])
        );
        $script->section('cached-prompt')->appendMessage(
            Message::fromString($cachedContext['prompt'])
        );
        $script->section('cached-examples')->appendMessages(
            $this->makeExamples($cachedContext['examples'])
        );

        return $this->filterEmptySections($script);
    }

    protected function withCacheMetaSections(Script $script) : Script {
        if (empty($this->request->cachedContext())) {
            return $script;
        }

        if ($script->section('cached-prompt')->notEmpty()) {
            $script->removeSection('prompt');
            $script->section('pre-cached-prompt')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "TASK:",
            ]);
        }

        if ($script->section('cached-examples')->notEmpty()) {
            $script->section('pre-cached-examples')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "EXAMPLES:",
            ]);
        }

        if ($script->section('cached-input')->notEmpty()) {
            $script->section('pre-cached-input')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "INPUT:",
            ]);
        }

        $script->section('post-cached')->appendMessageIfEmpty([
            'role' => 'user',
            'content' => [[
                'type' => 'text',
                'text' => 'INSTRUCTIONS:',
                'cache_control' => ["type" => "ephemeral"],
            ]],
        ]);

        return $script;
    }
}