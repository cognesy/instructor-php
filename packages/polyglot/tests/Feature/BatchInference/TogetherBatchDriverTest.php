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
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class TogetherBatchScriptedHttpClient implements CanSendHttpRequests, CanHandleHttpRequest
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
        return array_shift($this->responses) ?? throw new LogicException('No scripted Together response remains.');
    }
}

function togetherBatchFixture(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/together/'.$name);
}

it('runs a Together chat batch through the native upload, job and array-list protocols', function () {
    $completed = togetherBatchFixture('job-completed.json');
    $http = new TogetherBatchScriptedHttpClient([
        HttpResponse::sync(200, [], togetherBatchFixture('job-submitted.json')),
        HttpResponse::sync(200, [], '{"id":"batch_together_test","endpoint":"/v1/chat/completions","status":"IN_PROGRESS"}'),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::streamingFromIterable(200, [], str_split(togetherBatchFixture('output.jsonl'), 13)),
        HttpResponse::streamingFromIterable(200, [], str_split(togetherBatchFixture('error.jsonl'), 7)),
        HttpResponse::sync(200, [], togetherBatchFixture('list.json')),
    ]);
    $uploader = new class () implements CanUploadBatchFile {
        public ?BatchFileUpload $request = null;
        public string $body = '';

        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            $this->request = $request;
            $this->body = (string) file_get_contents($request->path());
            return new BatchFileUploadResponse(200, '{"id":"file_together_input"}');
        }
    };
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.together.xyz/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
        driver: 'together',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http, uploader: $uploader));
    $items = BatchItems::of(
        BatchItem::of('together-ok', new InferenceRequest(messages: Messages::fromString('A'))),
        BatchItem::of('together-failed', new InferenceRequest(messages: Messages::fromString('B'))),
    );

    $submitted = $batches->submit($items);
    $reference = BatchReference::fromArray($submitted->reference()->toArray());
    $cancel = $batches->cancel($reference);
    $finished = $batches->retrieve($reference);
    $outcomes = iterator_to_array($batches->results($reference)->items());
    $page = $batches->listJobs();

    $firstInput = json_decode(explode("\n", $uploader->body)[0], true, flags: JSON_THROW_ON_ERROR);
    $createBody = json_decode($http->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
    expect($config->apiBaseUrl())->toBe('https://api.together.ai/v1')
        ->and($submitted->status())->toBe(BatchStatus::Pending)
        ->and($reference->expectedCount())->toBe(2)
        ->and($cancel->alreadyTerminal())->toBeFalse()
        ->and($finished->status())->toBe(BatchStatus::Completed)
        ->and($finished->progress()->completed())->toBeNull()
        ->and($outcomes[0]->result()->unwrap()->message()->content()->toString())->toBe('Hello from Together')
        ->and($outcomes[1]->result()->isFailure())->toBeTrue()
        ->and($firstInput['custom_id'])->toBe('together-ok')
        ->and(array_key_exists('url', $firstInput))->toBeFalse()
        ->and(array_key_exists('method', $firstInput))->toBeFalse()
        ->and($createBody)->toBe(['input_file_id' => 'file_together_input', 'endpoint' => '/v1/chat/completions'])
        ->and($uploader->request->url())->toBe('https://api.together.ai/v1/files/upload')
        ->and($uploader->request->fields())->toBe(['purpose' => 'batch-api', 'file_name' => 'batch.jsonl'])
        ->and($page->nextCursor())->toBeNull()
        ->and(count(iterator_to_array($page->jobs())))->toBe(1)
        ->and($http->requests[6]->url())->toBe('https://api.together.ai/v1/batches');
});

it('keeps completed Together execution distinct from expired result retention', function () {
    $expiredArtifacts = togetherBatchFixture('job-completed-no-artifacts.json');
    $http = new TogetherBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $expiredArtifacts),
        HttpResponse::sync(200, [], $expiredArtifacts),
    ]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.together.xyz/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
        driver: 'together',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(
        new BatchJobId('batch_together_retained_expired'),
        'together',
        $config->scope(),
        '/v1/chat/completions',
        'together-chat',
    );

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);

    expect($job->status())->toBe(BatchStatus::Completed)
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->availability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->isAvailable())->toBeFalse()
        ->and($results->unavailableReason())->not->toBeNull()
        ->and(count($http->requests))->toBe(2);
});

it('reports a Together job past its processing window without result artifacts', function () {
    $expired = togetherBatchFixture('job-expired-no-artifacts.json');
    $http = new TogetherBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $expired),
        HttpResponse::sync(200, [], $expired),
    ]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.together.xyz/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
        driver: 'together',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(new BatchJobId('batch_together_expired'), 'together', $config->scope(), '/v1/chat/completions', 'together-chat');

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);

    expect($job->status())->toBe(BatchStatus::Expired)
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->availability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($http->requests)->toHaveCount(2);
});
