<?php
namespace Cognesy\Instructor\Features\Core\Data\Traits\ChatTemplate;

use Cognesy\Instructor\Features\Core\Data\Request;
use Cognesy\Instructor\Utils\Messages\Script;
use Exception;

trait HandlesScript
{
    protected function makeScript(Request $request) : Script {
        if ($this->isRequestEmpty($request)) {
            throw new Exception('Request cannot be empty - you have to provide content for processing.');
        }

        $script = new Script();

        // GET DATA
        $messages = $this->normalizeMessages($request->messages());

        // SYSTEM SECTION
        $script->section('system')->appendMessages(
            $this->makeSystem($messages, $request->system())
        );
        $script->section('messages')->appendMessages(
            $this->makeMessages($messages)
        );
        $script->section('input')->appendMessages(
            $this->makeInput($request->input())
        );
        $script->section('prompt')->appendMessage(
            $this->makePrompt($this->request->prompt())
        );
        $script->section('examples')->appendMessages(
            $this->makeExamples($request->examples())
        );

        return $this->filterEmptySections($script);
    }

    protected function withSections(Script $script) : Script {
        if ($script->section('prompt')->notEmpty()) {
            $script->section('pre-prompt')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "TASK:",
            ]);
        }

        if ($script->section('examples')->notEmpty()) {
            $script->section('pre-examples')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "EXAMPLES:",
            ]);
        }

        if ($script->section('input')->notEmpty()) {
            $script->section('pre-input')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "INPUT:",
            ]);
            $script->section('post-input')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "RESPONSE:",
            ]);
        }

        if ($script->section('retries')->notEmpty()) {
            $script->section('pre-retries')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "FEEDBACK:",
            ]);
            $script->section('post-retries')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "CORRECTED RESPONSE:",
            ]);
        }

        return $script;
    }

    private function isRequestEmpty(Request $request) : bool {
        return match(true) {
            !empty($request->messages()) => false,
            !empty($request->input()) => false,
            !empty($request->prompt()) => false,
            !empty($request->system()) => false, // ?
            !empty($request->examples()) => false, // ?
            default => true,
        };
    }
}