<?php declare(strict_types=1);

namespace Tests\Addons\Integration;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

describe('OpenAI Integration', function () {
    it('can complete a simple request using test preset', function () {
        $apiKey = getenv('OPENAI_API_KEY');
        if (empty($apiKey)) {
            $this->markTestSkipped('OPENAI_API_KEY not set');
        }

        $llm = LLMProvider::new()->withLLMPreset('test');
        $agent = AgentBuilder::base()
            ->withDriver(new \Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver(llm: $llm))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Say "Integration test passed"')
        );

        $finalState = $agent->finalStep($state);

        expect($finalState->currentStep()->outputMessages()->toString())->toContain('Integration test passed');
    })->group('integration');
});
