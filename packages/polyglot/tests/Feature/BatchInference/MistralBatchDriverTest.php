<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Config\MistralBatchOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanSendBatchFileBody;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Enums\MistralBatchInputMode;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileBodyRequest;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class MistralBatchScriptedHttpClient implements CanSendHttpRequests, CanHandleHttpRequest
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @param list<HttpResponse> $responses */
    public function __construct(private array $responses)
    {
    }
    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }
    public function handle(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        return array_shift($this->responses) ?? throw new LogicException('No scripted Mistral response remains.');
    }
}

function mistralBatchFixture(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/mistral/'.$name);
}

function mistralBatchConfig(): BatchConfig
{
    return BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.mistral.ai/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'mistral-test',
        driver: 'mistral',
    ));
}

it('runs file-backed Mistral jobs with separate output and error artifacts', function () {
    $success = mistralBatchFixture('job-success.json');
    $http = new MistralBatchScriptedHttpClient([
        HttpResponse::sync(200, [], mistralBatchFixture('job-submitted.json')),
        HttpResponse::sync(200, [], $success),
        HttpResponse::sync(200, [], '{"id":"job_test","status":"CANCELLATION_REQUESTED"}'),
        HttpResponse::sync(200, [], $success),
        HttpResponse::streamingFromIterable(200, [], str_split(mistralBatchFixture('output.jsonl'), 17)),
        HttpResponse::streamingFromIterable(200, [], str_split(mistralBatchFixture('error.jsonl'), 7)),
        HttpResponse::sync(200, [], mistralBatchFixture('list.json')),
    ]);
    $uploader = new class () implements CanUploadBatchFile {
        public array $lines = [];
        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            $this->lines = array_values(array_filter(explode("\n", (string) file_get_contents($request->path()))));
            return new BatchFileUploadResponse(200, '{"id":"file-input"}');
        }
    };
    $sender = new class () implements CanSendBatchFileBody {
        public function send(BatchFileBodyRequest $request): BatchFileUploadResponse
        {
            throw new LogicException('Inline sender should not run.');
        }
    };
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig(mistralBatchConfig(), http: $http, uploader: $uploader, bodySender: $sender));
    $items = BatchItems::of(
        BatchItem::of('item-ok', new InferenceRequest(messages: Messages::fromString('A'))),
        BatchItem::of('item-bad', new InferenceRequest(messages: Messages::fromString('B'))),
    );

    $submitted = $batches->submit($items);
    $finished = $batches->retrieve($submitted->reference());
    $receipt = $batches->cancel($submitted->reference());
    $outcomes = iterator_to_array($batches->results($submitted->reference())->items());
    $page = $batches->listJobs(2);
    $firstInput = json_decode($uploader->lines[0], true, flags: JSON_THROW_ON_ERROR);
    $createBody = json_decode($http->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);

    expect($submitted->status())->toBe(BatchStatus::Pending)
        ->and($submitted->reference()->codec())->toBe('mistral-chat-file')
        ->and($finished->status())->toBe(BatchStatus::Completed)
        ->and($receipt->providerStatus())->toBe('CANCELLATION_REQUESTED')
        ->and($outcomes[0]->result()->unwrap()->message()->content()->toString())->toBe('Bonjour!')
        ->and($outcomes[1]->result()->error()->kind())->toBe(BatchItemFailureKind::ProviderError)
        ->and($firstInput['body'])->not->toHaveKey('model')
        ->and($firstInput['custom_id'])->toBe('item-ok')
        ->and($createBody['model'])->toBe('mistral-test')
        ->and($createBody['input_files'])->toBe(['file-input'])
        ->and($page->nextCursor()?->token())->toBe('1')
        ->and(count($http->requests))->toBe(7);
});

it('sends Mistral inline requests with job fields in the streamed JSON object', function () {
    $inlineOutput = json_decode(mistralBatchFixture('output.jsonl'), true, flags: JSON_THROW_ON_ERROR);
    $job = [
        'id' => 'job_inline', 'status' => 'SUCCESS', 'endpoint' => '/v1/chat/completions',
        'total_requests' => 2, 'succeeded_requests' => 1, 'failed_requests' => 1,
        'outputs' => [$inlineOutput],
        'output_file' => 'file-inline-duplicate',
        'error_file' => 'file-inline-error',
    ];
    $http = new MistralBatchScriptedHttpClient([
        HttpResponse::sync(200, [], json_encode($job, JSON_THROW_ON_ERROR)),
        HttpResponse::streamingFromIterable(200, [], [mistralBatchFixture('error.jsonl')]),
    ]);
    $uploader = new class () implements CanUploadBatchFile {
        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            throw new LogicException('File uploader should not run.');
        }
    };
    $sender = new class () implements CanSendBatchFileBody {
        public array $body = [];
        public function send(BatchFileBodyRequest $request): BatchFileUploadResponse
        {
            $this->body = json_decode((string) file_get_contents($request->path()), true, flags: JSON_THROW_ON_ERROR);
            return new BatchFileUploadResponse(200, '{"id":"job_inline","status":"QUEUED","endpoint":"/v1/chat/completions"}');
        }
    };
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig(mistralBatchConfig(), http: $http, uploader: $uploader, bodySender: $sender));
    $reference = $batches->submit(
        BatchItems::of(
            BatchItem::of('item-ok', new InferenceRequest(messages: Messages::fromString('A'))),
            BatchItem::of('item-bad', new InferenceRequest(messages: Messages::fromString('B'))),
        ),
        new MistralBatchOptions(MistralBatchInputMode::Inline, timeoutHours: 48),
    )->reference();
    $results = iterator_to_array($batches->results($reference)->items());
    $result = $results[0];

    expect($reference->codec())->toBe('mistral-chat-inline')
        ->and(count($results))->toBe(2)
        ->and(count($http->requests))->toBe(2)
        ->and($sender->body['model'])->toBe('mistral-test')
        ->and($sender->body['endpoint'])->toBe('/v1/chat/completions')
        ->and($sender->body['timeout_hours'])->toBe(48)
        ->and($sender->body['requests'][0]['custom_id'])->toBe('item-ok')
        ->and($result->result()->unwrap()->message()->content()->toString())->toBe('Bonjour!')
        ->and($results[1]->key())->toBe('item-bad')
        ->and($results[1]->result()->isFailure())->toBeTrue()
        ->and($http->requests[0]->url())->toBe('https://api.mistral.ai/v1/batch/jobs/job_inline?inline=true');
});

it('keeps Mistral timeout artifacts readable without treating execution as success', function () {
    $timeout = mistralBatchFixture('job-timeout-artifacts.json');
    $http = new MistralBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $timeout),
        HttpResponse::sync(200, [], $timeout),
        HttpResponse::streamingFromIterable(200, [], [mistralBatchFixture('output.jsonl')]),
        HttpResponse::streamingFromIterable(200, [], [mistralBatchFixture('error.jsonl')]),
    ]);
    $config = mistralBatchConfig();
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(new BatchJobId('job_timeout'), 'mistral', $config->scope(), '/v1/chat/completions', 'mistral-chat-file', 2, true);

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);
    $items = iterator_to_array($results->items());

    expect($job->status())->toBe(BatchStatus::Expired)
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Final)
        ->and($results->availability())->toBe(BatchResultsAvailability::Final)
        ->and(count($items))->toBe(2)
        ->and($items[0]->result()->isSuccess())->toBeTrue()
        ->and($items[1]->result()->isFailure())->toBeTrue();
});

it('reports Mistral timeout without artifacts as unavailable', function () {
    $timeout = mistralBatchFixture('job-timeout-no-artifacts.json');
    $http = new MistralBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $timeout),
        HttpResponse::sync(200, [], $timeout),
    ]);
    $config = mistralBatchConfig();
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(new BatchJobId('job_timeout_unavailable'), 'mistral', $config->scope(), '/v1/chat/completions', 'mistral-chat-file', 2, true);

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);

    expect($job->status())->toBe(BatchStatus::Expired)
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->availability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->isAvailable())->toBeFalse()
        ->and(count($http->requests))->toBe(2);
});

it('reads completed Mistral items after terminal cancellation', function () {
    $cancelled = mistralBatchFixture('job-cancelled-artifacts.json');
    $http = new MistralBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $cancelled),
        HttpResponse::sync(200, [], $cancelled),
        HttpResponse::streamingFromIterable(200, [], [mistralBatchFixture('output.jsonl')]),
    ]);
    $config = mistralBatchConfig();
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(new BatchJobId('job_cancelled'), 'mistral', $config->scope(), '/v1/chat/completions', 'mistral-chat-file', 2, true);

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);
    $items = iterator_to_array($results->items());

    expect($job->status())->toBe(BatchStatus::Cancelled)
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Final)
        ->and($results->availability())->toBe(BatchResultsAvailability::Final)
        ->and($items)->toHaveCount(1)
        ->and($items[0]->key())->toBe('item-ok')
        ->and($items[0]->result()->isSuccess())->toBeTrue()
        ->and($http->requests)->toHaveCount(3);
});
