<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Core\InferenceResponseEventPayload;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\SupportStatus;

function profileCapturingDriver(array &$requests): CanProcessInferenceRequest
{
    return new class($requests) implements CanProcessInferenceRequest {
        /** @param array<InferenceRequest> $requests */
        public function __construct(private array &$requests) {}

        public function makeResponseFor(InferenceRequest $request): InferenceResponse
        {
            $this->requests[] = $request;
            return InferenceResponse::empty();
        }

        /** @return iterable<PartialInferenceDelta> */
        public function makeStreamDeltasFor(InferenceRequest $request): iterable
        {
            $this->requests[] = $request;
            return [];
        }
    };
}

function requestModelCatalog(): ModelCatalog
{
    return ModelCatalog::fromArray([
        'version' => 'runtime-test-v1',
        'models' => [
            [
                'driver' => 'openai',
                'model' => 'config-model',
                'status' => 'supported',
                'source' => 'test-config',
            ],
            [
                'driver' => 'openai',
                'model' => 'request-model',
                'status' => 'supported',
                'capabilities' => [
                    'jsonObject' => 'supported',
                    'jsonSchema' => 'unsupported',
                ],
                'source' => 'test-request',
            ],
        ],
    ]);
}

it('keeps ordinary inference independent of model knowledge', function () {
    $requests = [];
    $runtime = new InferenceRuntime(
        driver: profileCapturingDriver($requests),
        events: new EventDispatcher,
        driverName: 'custom',
        defaultModel: 'configured-model',
    );

    $runtime->create(new InferenceRequest(messages: Messages::fromString('configured')))->response();
    $runtime->create(new InferenceRequest(
        messages: Messages::fromString('override'),
        model: 'uncataloged-model',
    ))->response();

    expect($requests)->toHaveCount(2)
        ->and($requests[0]->model())->toBe('configured-model')
        ->and($requests[0]->modelProfile())->toBeNull()
        ->and($requests[1]->model())->toBe('uncataloged-model')
        ->and($requests[1]->modelProfile())->toBeNull();
});

it('resolves the configured model when a request has no override', function () {
    $requests = [];
    $runtime = new InferenceRuntime(
        driver: profileCapturingDriver($requests),
        events: new EventDispatcher,
        models: requestModelCatalog(),
        driverName: 'openai',
        defaultModel: 'config-model',
    );

    $runtime->create(new InferenceRequest(messages: Messages::fromString('hello')))->response();

    expect($requests[0]->model())->toBe('config-model')
        ->and($requests[0]->modelProfile()?->key->model)->toBe('config-model')
        ->and($requests[0]->modelProfile()?->source)->toBe('test-config');
});

it('resolves the request model after a model override', function () {
    $requests = [];
    $runtime = new InferenceRuntime(
        driver: profileCapturingDriver($requests),
        events: new EventDispatcher,
        models: requestModelCatalog(),
        driverName: 'openai',
        defaultModel: 'config-model',
    );

    $runtime->create(new InferenceRequest(
        messages: Messages::fromString('hello'),
        model: 'request-model',
    ))->response();

    expect($requests[0]->modelProfile()?->key->model)->toBe('request-model')
        ->and($requests[0]->modelProfile()?->source)->toBe('test-request');
});

it('attaches explicit unknown facts for an unknown offering', function () {
    $requests = [];
    $runtime = new InferenceRuntime(
        driver: profileCapturingDriver($requests),
        events: new EventDispatcher,
        models: requestModelCatalog(),
        driverName: 'custom',
        defaultModel: 'private-model',
    );

    $runtime->create(new InferenceRequest(messages: Messages::fromString('hello')))->response();

    expect($requests[0]->modelProfile()?->status)->toBe(SupportStatus::Unknown)
        ->and($requests[0]->modelProfile()?->key->toString())->toBe('custom/private-model');
});

it('runs capability preflight after resolving the effective model profile', function () {
    $requests = [];
    $runtime = new InferenceRuntime(
        driver: profileCapturingDriver($requests),
        events: new EventDispatcher,
        models: requestModelCatalog(),
        driverName: 'openai',
        defaultModel: 'config-model',
        allowLossyFallback: true,
    );

    $runtime->create(new InferenceRequest(
        model: 'request-model',
        responseFormat: ResponseFormat::jsonSchema(['type' => 'object']),
    ))->response();

    expect($requests[0]->model())->toBe('request-model')
        ->and($requests[0]->responseFormat()->toArray())->toBe(['type' => 'json_object']);
});

it('keeps the resolved profile out of request serialization', function () {
    $request = (new InferenceRequest(model: 'request-model'))
        ->withModelProfile(requestModelCatalog()->find('openai', 'request-model'));

    expect($request->toArray())->not->toHaveKey('modelProfile')
        ->not->toHaveKey('model_profile');
});

it('projects executed offering identity into response telemetry', function () {
    $request = (new InferenceRequest(model: 'request-model'))
        ->withModelProfile(requestModelCatalog()->find('openai', 'request-model'));

    $payload = InferenceResponseEventPayload::build(
        InferenceResponse::empty(),
        $request,
        'execution-id',
    );

    expect($payload)->toMatchArray([
        'model' => 'request-model',
        'modelKey' => 'openai/request-model',
        'modelCatalogVersion' => 'runtime-test-v1',
        'modelCatalogSource' => 'test-request',
        'modelSupportStatus' => 'supported',
    ]);
});
