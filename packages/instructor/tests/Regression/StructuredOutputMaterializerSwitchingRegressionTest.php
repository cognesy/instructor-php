<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Core\StructuredPromptRequestMaterializer;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\PendingInference;

final class SwitchingRegressionUser
{
    public string $name = '';
}

it('materializes cached context and live task through an injected request materializer', function () {
    $run = function (CanMaterializeRequest $materializer): array {
        $inference = new class implements CanCreateInference {
            public ?InferenceRequest $captured = null;

            public function create(?InferenceRequest $request = null): PendingInference
            {
                $this->captured = $request;
                assert($request instanceof InferenceRequest);

                return new PendingInference(
                    execution: InferenceExecution::fromRequest($request),
                    driver: new \Cognesy\Instructor\Tests\Support\FakeInferenceDriver([
                        new InferenceResponse(content: '{"name":"Switched"}'),
                    ]),
                    eventDispatcher: new EventDispatcher(),
                );
            }
        };

        $runtime = (new StructuredOutputRuntime(
            inference: $inference,
            events: new EventDispatcher(),
            config: new StructuredOutputConfig(outputMode: \Cognesy\Instructor\Enums\OutputMode::Json),
        ))->withRequestMaterializer($materializer);

        $result = (new StructuredOutput($runtime))
            ->withResponseClass(SwitchingRegressionUser::class)
            ->intoArray()
            ->withCachedContext(
                messages: Messages::fromString('Cached conversation.'),
                system: 'Cached system.',
                prompt: 'CACHED TASK',
            )
            ->with(
                messages: 'Live conversation.',
                responseModel: SwitchingRegressionUser::class,
                system: 'Live system.',
                prompt: 'LIVE TASK',
            )
            ->get();

        return [$result, $inference->captured];
    };

    [$structuredResult, $structuredRequest] = $run(new StructuredPromptRequestMaterializer());

    expect($structuredResult)->toBe(['name' => 'Switched'])
        ->and($structuredRequest)->toBeInstanceOf(InferenceRequest::class)
        ->and($structuredRequest?->cachedContext()?->isEmpty())->toBeFalse()
        ->and($structuredRequest?->messages()->first()?->toString())->toContain('LIVE TASK')
        ->and($structuredRequest?->cachedContext()?->messages()->first()?->toString())->toContain('CACHED TASK');
});
