<?php
require 'vendor/autoload.php';

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Time\FrozenClock;

class TestDriver implements CanHandleInference
{
    public function makeResponseFor(InferenceRequest $request): InferenceResponse {
        return new InferenceResponse(content: '{bad json');
    }

    public function makeStreamResponsesFor(InferenceRequest $request): iterable {
        return [];
    }

    public function toHttpRequest(InferenceRequest $request): \Cognesy\Http\Data\HttpRequest {
        return new \Cognesy\Http\Data\HttpRequest('http://test', 'POST', [], [], []);
    }

    public function httpResponseToInference(\Cognesy\Http\Contracts\HttpResponse $httpResponse): InferenceResponse {
        return new InferenceResponse(content: '');
    }

    public function httpResponseToInferenceStream(\Cognesy\Http\Contracts\HttpResponse $httpResponse): iterable {
        return [];
    }
}

function _noop(): string {
    return 'ok';
}

echo "Creating driver...\n";
$driver = new TestDriver();

echo "Creating react...\n";
$react = new ReActDriver(llm: LLMProvider::new()->withDriver($driver));

echo "Creating tools...\n";
$tools = new Tools(FunctionTool::fromCallable(_noop(...)));

echo "Creating state...\n";
$state = new ToolUseState();

echo "Creating clock...\n";
$clock = FrozenClock::at('2024-01-01 12:00:00');

echo "Creating criteria...\n";
$continuationCriteria = new ContinuationCriteria(
    new StepsLimit(3, fn(ToolUseState $s) => $s->stepCount()),
    new ExecutionTimeLimit(30, fn(ToolUseState $s) => $s->startedAt(), $clock),
);

echo "Creating toolUse...\n";
$toolUse = ToolUseFactory::default(
    tools: $tools,
    driver: $react,
    continuationCriteria: $continuationCriteria,
);

echo "Running nextStep...\n";
$result = $toolUse->nextStep($state);

echo "Done! Status: " . $result->status()->name . "\n";
echo "Has errors: " . ($result->currentStep()?->hasErrors() ? 'yes' : 'no') . "\n";
if ($result->currentStep()?->hasErrors()) {
    echo "Error: " . $result->currentStep()->errorsAsString() . "\n";
}