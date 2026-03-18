<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\PendingInference;

final class RuntimeRequestMaterializerUser
{
    public string $name = '';
}

it('uses custom request materializer when provided on runtime', function () {
    $materializer = new class implements CanMaterializeRequest {
        public bool $called = false;

        public function toInferenceRequest(StructuredOutputExecution $execution): InferenceRequest
        {
            $this->called = true;

            return new InferenceRequest(
                messages: Messages::fromString('Materialized override', 'system'),
            );
        }
    };

    $inference = new class implements CanCreateInference {
        public ?InferenceRequest $captured = null;

        public function create(InferenceRequest $request): PendingInference
        {
            $this->captured = $request;

            return new PendingInference(
                execution: InferenceExecution::fromRequest($request),
                driver: new FakeInferenceDriver([
                    new InferenceResponse(content: '{"name":"Materialized"}'),
                ]),
                eventDispatcher: new EventDispatcher(),
            );
        }
    };

    $runtime = (new StructuredOutputRuntime(
        inference: $inference,
        events: new EventDispatcher(),
        config: new StructuredOutputConfig(outputMode: OutputMode::Json),
    ))->withRequestMaterializer($materializer);

    $result = (new StructuredOutput($runtime))
        ->withResponseClass(RuntimeRequestMaterializerUser::class)
        ->intoArray()
        ->with(messages: 'ignored input')
        ->get();

    expect($result)->toBe(['name' => 'Materialized'])
        ->and($runtime->requestMaterializer())->toBe($materializer)
        ->and($materializer->called)->toBeTrue()
        ->and($inference->captured)->toBeInstanceOf(InferenceRequest::class)
        ->and($inference->captured?->messages()->count())->toBe(1)
        ->and($inference->captured?->messages()->first()?->role()->value)->toBe('system')
        ->and($inference->captured?->messages()->first()?->toString())->toBe('Materialized override');
});
