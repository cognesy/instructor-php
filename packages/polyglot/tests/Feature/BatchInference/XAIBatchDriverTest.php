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
use Cognesy\Polyglot\BatchInference\Config\XAIBatchOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class XAIBatchScriptedHttpClient implements CanSendHttpRequests, CanHandleHttpRequest
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
        return array_shift($this->responses) ?? throw new LogicException('No scripted xAI response remains.');
    }
}

function xaiBatchFixture(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/xai/'.$name);
}

function xaiBatchConfig(): BatchConfig
{
    return BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.x.ai/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'grok-4.3',
        driver: 'xai',
    ));
}

it('keeps sealed xAI jobs pending until ingestion, reads paginated results, and lists open jobs', function () {
    $partial = xaiBatchFixture('job-partial.json');
    $completed = xaiBatchFixture('job-completed.json');
    $http = new XAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], xaiBatchFixture('job-submitted.json')),
        HttpResponse::sync(200, [], xaiBatchFixture('job-submitted.json')),
        HttpResponse::sync(200, [], $partial),
        HttpResponse::sync(200, [], xaiBatchFixture('results-partial.json')),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::sync(200, [], xaiBatchFixture('results-first.json')),
        HttpResponse::sync(200, [], xaiBatchFixture('results-last.json')),
        HttpResponse::sync(200, [], xaiBatchFixture('list.json')),
        HttpResponse::sync(200, [], '{"batches":[],"pagination_token":null}'),
    ]);
    $uploader = new class () implements CanUploadBatchFile {
        public ?BatchFileUpload $request = null;
        public string $body = '';

        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            $this->request = $request;
            $this->body = (string) file_get_contents($request->path());
            return new BatchFileUploadResponse(200, '{"id":"file_xai_input"}');
        }
    };
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig(xaiBatchConfig(), http: $http, uploader: $uploader));
    $items = BatchItems::of(
        BatchItem::of('xai-ok', new InferenceRequest(messages: Messages::fromString('A'))),
        BatchItem::of('xai-error', new InferenceRequest(messages: Messages::fromString('B'))),
    );

    $submitted = $batches->submit($items, new XAIBatchOptions('test-xai'));
    $reference = BatchReference::fromArray($submitted->reference()->toArray());
    $ingesting = $batches->retrieve($reference);
    $partialResults = $batches->results($reference);
    $partialItems = iterator_to_array($partialResults->items());
    $finished = $batches->retrieve($reference);
    $finalResults = $batches->results($reference);
    $finalItems = iterator_to_array($finalResults->items());
    $page = $batches->listJobs(limit: 2);
    $nextPage = $batches->listJobs(limit: 2, cursor: $page->nextCursor());
    $listedJobs = iterator_to_array($page->jobs());

    $lines = array_values(array_filter(explode("\n", $uploader->body)));
    $firstLine = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
    $createBody = json_decode($http->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
    expect($submitted->status())->toBe(BatchStatus::Pending)
        ->and($ingesting->status())->toBe(BatchStatus::Pending)
        ->and($reference->expectedCount())->toBe(2)
        ->and($reference->inputClosed())->toBeTrue()
        ->and($partialResults->availability())->toBe(BatchResultsAvailability::Partial)
        ->and($partialItems[0]->key())->toBe('xai-ok')
        ->and(count($partialItems))->toBe(1)
        ->and($finished->status())->toBe(BatchStatus::Completed)
        ->and($finalResults->availability())->toBe(BatchResultsAvailability::Final)
        ->and($finalItems[0]->result()->unwrap()->message()->content()->toString())->toBe('Hello from xAI')
        ->and($finalItems[1]->result()->isFailure())->toBeTrue()
        ->and($firstLine['custom_id'])->toBe('xai-ok')
        ->and($firstLine['url'])->toBe('/v1/chat/completions')
        ->and($createBody)->toBe(['name' => 'test-xai', 'input_file_id' => 'file_xai_input'])
        ->and($uploader->request->url())->toBe('https://api.x.ai/v1/files')
        ->and($listedJobs[0]->status())->toBe(BatchStatus::Running)
        ->and($page->nextCursor()?->token())->toBe('xai-job-next')
        ->and($nextPage->nextCursor())->toBeNull()
        ->and($http->requests[7]->url())->toBe('https://api.x.ai/v1/batches/batch_xai_test/results?limit=100&pagination_token=xai-result-next')
        ->and($http->requests[9]->url())->toBe('https://api.x.ai/v1/batches?limit=2&pagination_token=xai-job-next');
});

it('keeps an imported xAI open container nonterminal with no pending requests', function () {
    $http = new XAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], xaiBatchFixture('list.json')),
    ]);
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig(xaiBatchConfig(), http: $http));
    $page = $batches->listJobs();
    $jobs = iterator_to_array($page->jobs());

    expect($jobs[0]->isTerminal())->toBeFalse()
        ->and($jobs[0]->reference()->inputClosed())->toBeFalse()
        ->and($jobs[0]->resultsAvailability())->toBe(BatchResultsAvailability::Partial);
});

it('preserves completed xAI outcomes after cancellation acknowledgement', function () {
    $cancelled = '{"batch_id":"batch_xai_test","cancel_time":"2026-10-07T10:01:00Z","state":{"num_requests":2,"num_pending":0,"num_success":1,"num_error":0,"num_cancelled":1}}';
    $http = new XAIBatchScriptedHttpClient([HttpResponse::sync(200, [], $cancelled)]);
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig(xaiBatchConfig(), http: $http));
    $reference = new BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_xai_test'),
        'xai',
        xaiBatchConfig()->scope(),
        '/v1/chat/completions',
        'xai-chat',
        2,
        true,
    );

    $receipt = $batches->cancel($reference);
    expect($receipt->acknowledged())->toBeTrue()
        ->and($receipt->providerStatus())->toBe('cancelled')
        ->and($receipt->alreadyTerminal())->toBeTrue()
        ->and($http->requests[0]->url())->toBe('https://api.x.ai/v1/batches/batch_xai_test:cancel');
});

it('handles an empty xAI result page and permits repeated outcomes on a later snapshot', function () {
    $partial = xaiBatchFixture('job-partial.json');
    $http = new XAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $partial),
        HttpResponse::sync(200, [], '{"results":[],"pagination_token":"after-empty"}'),
        HttpResponse::sync(200, [], xaiBatchFixture('results-partial.json')),
        HttpResponse::sync(200, [], $partial),
        HttpResponse::sync(200, [], xaiBatchFixture('results-partial.json')),
    ]);
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig(xaiBatchConfig(), http: $http));
    $reference = new BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_xai_test'),
        'xai',
        xaiBatchConfig()->scope(),
        '/v1/chat/completions',
        'xai-chat',
        2,
        true,
    );

    $first = iterator_to_array($batches->results($reference)->items());
    $second = iterator_to_array($batches->results($reference)->items());

    expect($first[0]->key())->toBe('xai-ok')
        ->and($second[0]->key())->toBe('xai-ok')
        ->and($http->requests[2]->url())->toBe('https://api.x.ai/v1/batches/batch_xai_test/results?limit=100&pagination_token=after-empty');
});

it('keeps completed xAI execution while refusing results after batch expiry', function () {
    $expired = xaiBatchFixture('job-results-expired.json');
    $http = new XAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $expired),
        HttpResponse::sync(200, [], $expired),
    ]);
    $config = xaiBatchConfig();
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_xai_test'),
        'xai',
        $config->scope(),
        $config->route(),
        'xai-chat',
        2,
        true,
    );

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);

    expect($job->status())->toBe(BatchStatus::Completed)
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->availability())->toBe(BatchResultsAvailability::Unavailable)
        ->and(fn () => iterator_to_array($results->items()))->toThrow(LogicException::class, 'Batch results are not available.')
        ->and($http->requests)->toHaveCount(2);
});

it('reports an unfinished xAI batch as expired after its deadline', function () {
    $http = new XAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], xaiBatchFixture('job-expired-pending.json')),
    ]);
    $config = xaiBatchConfig();
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_xai_test'),
        'xai',
        $config->scope(),
        $config->route(),
        'xai-chat',
        2,
        true,
    );

    $job = $batches->retrieve($reference);

    expect($job->status())->toBe(BatchStatus::Expired)
        ->and($job->isTerminal())->toBeTrue()
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Unavailable);
});
