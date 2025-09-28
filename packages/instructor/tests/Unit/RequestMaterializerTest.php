<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

describe('RequestMaterializer', function () {
    function makeConfig(): StructuredOutputConfig {
        return new StructuredOutputConfig();
    }

    function makeRequest(
        string|array|Messages|null $messages = null,
        ?string $system = '',
        ?string $prompt = '',
        array $examples = [],
        ?CachedContext $cached = null,
        ?StructuredOutputConfig $config = null,
    ): StructuredOutputRequest {
        $cfg = $config ?? makeConfig();
        return new StructuredOutputRequest(
            messages: $messages ?? '',
            requestedSchema: [],
            responseModel: null,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: null,
            options: [],
            cachedContext: $cached ?? new CachedContext(),
            config: $cfg,
        );
    }

    function makeExecution(
        ?StructuredOutputRequest $request = null,
        ?StructuredOutputConfig $config = null,
    ): StructuredOutputExecution {
        return (new StructuredOutputExecutionBuilder(new EventDispatcher()))->createWith(
            request: $request ?? makeRequest(),
            config: $config ?? makeConfig(),
        );
    }

    it('preserves explicit system and prompt', function () {
        $materializer = new RequestMaterializer(makeConfig());
        $request = makeRequest(messages: null, system: 'You are helpful.', prompt: 'Say hi.');
        $execution = makeExecution(request: $request);

        $out = $materializer->toMessages($execution);

        expect($out)->not->toBeEmpty();
        $roles = array_map(fn($m) => $m['role'] ?? '', $out);
        if (!in_array('system', $roles, true)) {
            throw new Exception('roles=' . json_encode($roles) . ' out=' . json_encode($out));
        }
        // Prompt-related entries should be present (pre-prompt label and user prompt)
        $hasPrompt = false;
        foreach ($out as $m) {
            if (($m['role'] ?? '') === 'user' && (($m['content'] ?? '') === 'Say hi.' || ($m['content'] ?? '') === 'TASK:')) {
                $hasPrompt = true; break;
            }
        }
        expect($hasPrompt)->toBeTrue();
    });

    it('includes cached system with cache_control only when present', function () {
        $materializer = new RequestMaterializer(makeConfig());
        $cached = new CachedContext(messages: [], system: 'Cached system', prompt: '', examples: []);
        $request = makeRequest(messages: null, system: '', prompt: 'Say hi.', cached: $cached);
        $execution = makeExecution(request: $request);

        $out = $materializer->toMessages($execution);

        // There must be a system message containing the cached system text
        $system = array_values(array_filter($out, fn($m) => ($m['role'] ?? '') === 'system'));
        expect($system)->not->toBeEmpty();
        $hasCacheControl = false;
        foreach ($system as $m) {
            if (is_array($m['content'] ?? null)) {
                $part = $m['content'][0] ?? [];
                if (($part['type'] ?? '') === 'text' && ($part['text'] ?? '') === 'Cached system' && isset($part['cache_control'])) {
                    $hasCacheControl = true; break;
                }
            }
        }
        expect($hasCacheControl)->toBeTrue();
    });

    it('uses cached prompt and removes original prompt, adding meta sections', function () {
        $materializer = new RequestMaterializer(makeConfig());
        $cached = new CachedContext(messages: [], system: '', prompt: 'C_PROMPT', examples: []);
        $request = makeRequest(messages: null, system: '', prompt: 'REQ_PROMPT', cached: $cached);
        $execution = makeExecution(request: $request);

        $out = $materializer->toMessages($execution);

        $hasCachedPrompt = false;
        $hasTaskLabel = false;
        $hasInstructionsLabel = false;
        foreach ($out as $m) {
            if (($m['role'] ?? '') !== 'user') { continue; }
            $c = $m['content'] ?? '';
            if (is_array($c)) {
                $part = $c[0] ?? [];
                if (($part['type'] ?? '') === 'text' && ($part['text'] ?? '') === 'C_PROMPT') {
                    $hasCachedPrompt = true;
                }
            } else {
                if ($c === 'TASK:') { $hasTaskLabel = true; }
                if ($c === 'INSTRUCTIONS:') { $hasInstructionsLabel = true; }
                if ($c === 'REQ_PROMPT') { throw new Exception('Original prompt should be removed when cached prompt present'); }
            }
        }
        expect($hasCachedPrompt)->toBeTrue();
        expect($hasTaskLabel)->toBeTrue();
        expect($hasInstructionsLabel)->toBeTrue();
    });

    it('adds retries with feedback and corrected response sections', function () {
        $cfg = makeConfig();
        $materializer = new RequestMaterializer($cfg);
        $request = makeRequest(messages: [['role' => 'user', 'content' => 'foo']], system: '', prompt: '');
        $execution = makeExecution(request: $request);

        // Simulate a failed attempt
        $execution->withFailedAttempt(
            messages: [['role' => 'assistant', 'content' => 'bad json']],
            inferenceResponse: new InferenceResponse(content: '{bad json}'),
            errors: ['Missing field x']
        );

        $out = $materializer->toMessages($execution);

        $hasFeedback = false; $hasCorrected = false;
        foreach ($out as $m) {
            if (($m['role'] ?? '') !== 'user') { continue; }
            if (($m['content'] ?? '') === 'FEEDBACK:') { $hasFeedback = true; }
            if (($m['content'] ?? '') === 'CORRECTED RESPONSE:') { $hasCorrected = true; }
        }
        expect($hasFeedback)->toBeTrue();
        expect($hasCorrected)->toBeTrue();
    });
});
