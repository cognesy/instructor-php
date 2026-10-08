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
use Cognesy\Polyglot\BatchInference\Config\GeminiBatchOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanSendBatchFileBody;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Enums\GeminiBatchInputMode;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileBodyRequest;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class GeminiBatchScriptedHttpClient implements CanSendHttpRequests, CanHandleHttpRequest
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
        return array_shift($this->responses) ?? throw new LogicException('No scripted Gemini response remains.');
    }
}

function geminiBatchFixture(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/gemini/'.$name);
}

function geminiBatchConfig(): BatchConfig
{
    return BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://generativelanguage.googleapis.com/v1beta',
        apiKey: 'test-secret',
        endpoint: '/models/{model}:generateContent',
        model: 'gemini-2.5-flash-lite',
        driver: 'gemini',
    ));
}

function geminiBatchItems(): BatchItems
{
    return BatchItems::of(
        BatchItem::of('gemini-ok', new InferenceRequest(messages: Messages::fromString('A'))),
        BatchItem::of('gemini-failed', new InferenceRequest(messages: Messages::fromString('B'))),
    );
}

it('maps the BATCH_STATE values returned by the native operation API', function () {
    $config = geminiBatchConfig();
    $operation = static fn (string $state, bool $done): string => json_encode([
        'name' => 'batches/gemini-live',
        'metadata' => ['model' => 'models/gemini-2.5-flash-lite', 'state' => $state, 'batchStats' => ['requestCount' => '2']],
        'done' => $done,
    ], JSON_THROW_ON_ERROR);
    $http = new GeminiBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $operation('BATCH_STATE_RUNNING', false)),
        HttpResponse::sync(200, [], $operation('BATCH_STATE_SUCCEEDED', true)),
    ]);
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batches/gemini-live'),
        'gemini',
        $config->scope(),
        'models/gemini-2.5-flash-lite:batchGenerateContent',
        'gemini-generate-content',
        2,
        true,
    );

    expect($batches->retrieve($reference)->status())->toBe(BatchStatus::Running)
        ->and($batches->retrieve($reference)->status())->toBe(BatchStatus::Completed);
});

it('reports failed and expired Gemini operations without output as unavailable', function () {
    $config = geminiBatchConfig();
    foreach ([
        ['file-failed.json', 'batches/gemini-failed', BatchStatus::Failed, '3'],
        ['file-expired.json', 'batches/gemini-expired', BatchStatus::Expired, null],
    ] as [$fixture, $id, $status, $failureCode]) {
        $operation = geminiBatchFixture($fixture);
        $http = new GeminiBatchScriptedHttpClient([
            HttpResponse::sync(200, [], $operation),
            HttpResponse::sync(200, [], $operation),
        ]);
        $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
        $reference = new BatchReference(
            new \Cognesy\Polyglot\BatchInference\Data\BatchJobId($id),
            'gemini',
            $config->scope(),
            'models/gemini-2.5-flash-lite:batchGenerateContent',
            'gemini-generate-content',
            2,
            true,
        );

        $job = $batches->retrieve($reference);
        $results = $batches->results($reference);

        expect($job->status())->toBe($status)
            ->and($job->failureCode())->toBe($failureCode)
            ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Unavailable)
            ->and($results->availability())->toBe(BatchResultsAvailability::Unavailable)
            ->and($results->isAvailable())->toBeFalse()
            ->and(count($http->requests))->toBe(2);
    }
});

it('does not report an empty Gemini inline container as available results', function () {
    $config = geminiBatchConfig();
    foreach ([
        ['BATCH_STATE_RUNNING', false, BatchResultsAvailability::Pending],
        ['BATCH_STATE_SUCCEEDED', true, BatchResultsAvailability::Unavailable],
    ] as [$state, $done, $availability]) {
        $operation = json_encode([
            'name' => 'batches/gemini-empty',
            'metadata' => ['model' => 'models/gemini-2.5-flash-lite', 'state' => $state, 'batchStats' => ['requestCount' => '2']],
            'done' => $done,
            'response' => ['inlinedResponses' => ['inlinedResponses' => []]],
        ], JSON_THROW_ON_ERROR);
        $http = new GeminiBatchScriptedHttpClient([
            HttpResponse::sync(200, [], $operation),
            HttpResponse::sync(200, [], $operation),
        ]);
        $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
        $reference = new BatchReference(
            new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batches/gemini-empty'),
            'gemini',
            $config->scope(),
            'models/gemini-2.5-flash-lite:batchGenerateContent',
            'gemini-generate-content',
            2,
            true,
        );

        $job = $batches->retrieve($reference);
        $results = $batches->results($reference);

        expect($job->resultsAvailability())->toBe($availability)
            ->and($results->availability())->toBe($availability)
            ->and($results->isAvailable())->toBeFalse()
            ->and(fn () => iterator_to_array($results->items()))->toThrow(LogicException::class);
    }
});

it('runs keyed Gemini inline batches through operation status, empty cancel acknowledgement and listing', function () {
    $completed = geminiBatchFixture('inline-completed.json');
    $http = new GeminiBatchScriptedHttpClient([
        HttpResponse::sync(200, [], '{}'),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::sync(200, [], geminiBatchFixture('list.json')),
        HttpResponse::sync(200, [], '{"operations":[],"nextPageToken":""}'),
    ]);
    $sender = new class () implements CanSendBatchFileBody {
        public ?BatchFileBodyRequest $request = null;
        public string $body = '';

        public function send(BatchFileBodyRequest $request): BatchFileUploadResponse
        {
            $this->request = $request;
            $this->body = (string) file_get_contents($request->path());
            return new BatchFileUploadResponse(200, geminiBatchFixture('inline-submitted.json'));
        }
    };
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig(geminiBatchConfig(), http: $http, bodySender: $sender));

    $submitted = $batches->submit(geminiBatchItems(), new GeminiBatchOptions(GeminiBatchInputMode::Inline, 'inline-run'));
    $reference = BatchReference::fromArray(json_decode(json_encode($submitted->reference()->toArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
    $cancel = $batches->cancel($reference);
    $finished = $batches->retrieve($reference);
    $results = $batches->results($reference);
    $outcomes = iterator_to_array($results->items());
    $page = $batches->listJobs(limit: 2);
    $batches->listJobs(limit: 2, cursor: $page->nextCursor());

    $body = json_decode($sender->body, true, flags: JSON_THROW_ON_ERROR);
    $first = $body['batch']['inputConfig']['requests']['requests'][0];
    expect($submitted->reference()->id()->toString())->toBe('batches/gemini-inline')
        ->and($reference->manifest()?->toArray())->toBe(['gemini-ok', 'gemini-failed'])
        ->and($cancel->alreadyTerminal())->toBeFalse()
        ->and($finished->status())->toBe(BatchStatus::Completed)
        ->and($finished->progress()->failed())->toBe(1)
        ->and($results->availability())->toBe(BatchResultsAvailability::Final)
        ->and($outcomes[0]->key())->toBe('gemini-ok')
        ->and($outcomes[0]->result()->unwrap()->message()->content()->toString())->toContain('Hello from Gemini')
        ->and($outcomes[1]->key())->toBe('gemini-failed')
        ->and($outcomes[1]->result()->isFailure())->toBeTrue()
        ->and($first['metadata']['key'])->toBe('gemini-ok')
        ->and($first['request']['contents'][0]['parts'][0]['text'])->toBe('A')
        ->and($sender->request->url())->toBe('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:batchGenerateContent')
        ->and($page->nextCursor()?->token())->toBe('gemini-next')
        ->and($http->requests[0]->url())->toBe('https://generativelanguage.googleapis.com/v1beta/batches/gemini-inline:cancel')
        ->and($http->requests[4]->url())->toBe('https://generativelanguage.googleapis.com/v1beta/batches?pageSize=2&pageToken=gemini-next');
});

it('uploads a Gemini JSONL file resumably and recovers ordinal results after reference serialization', function () {
    $completed = geminiBatchFixture('file-completed.json');
    $http = new GeminiBatchScriptedHttpClient([
        HttpResponse::sync(200, ['X-Goog-Upload-URL' => 'https://generativelanguage.googleapis.com/upload/v1beta/files?upload_id=test'], ''),
        HttpResponse::sync(200, [], geminiBatchFixture('file-submitted.json')),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::streamingFromIterable(200, [], str_split(geminiBatchFixture('file-results.jsonl'), 17)),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::streamingFromIterable(200, [], str_split(geminiBatchFixture('file-results.jsonl'), 17)),
    ]);
    $sender = new class () implements CanSendBatchFileBody {
        public ?BatchFileBodyRequest $request = null;
        public string $body = '';
        public function send(BatchFileBodyRequest $request): BatchFileUploadResponse
        {
            $this->request = $request;
            $this->body = (string) file_get_contents($request->path());
            return new BatchFileUploadResponse(200, '{"file":{"name":"files/gemini-input"}}');
        }
    };
    $config = geminiBatchConfig();
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http, bodySender: $sender));
    $submitted = $batches->submit(geminiBatchItems());
    $reference = BatchReference::fromArray($submitted->reference()->toArray());
    $results = $batches->results($reference);
    $outcomes = iterator_to_array($results->items());
    $withoutManifest = new BatchReference($reference->id(), 'gemini', $config->scope(), $reference->route(), $reference->codec(), 2, true);

    $lines = array_values(array_filter(explode("\n", $sender->body)));
    $first = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
    expect($reference->manifest()?->count())->toBe(2)
        ->and($first['key'])->toBe('gemini-ok')
        ->and($first['request']['contents'][0]['parts'][0]['text'])->toBe('A')
        ->and($sender->request->url())->toContain('upload_id=test')
        ->and(array_key_exists('x-goog-api-key', $sender->request->headers()))->toBeFalse()
        ->and($http->requests[1]->url())->toBe('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:batchGenerateContent')
        ->and($outcomes[0]->key())->toBe('gemini-ok')
        ->and($outcomes[0]->result()->unwrap()->message()->content()->toString())->toContain('First ordinal result')
        ->and($outcomes[1]->key())->toBe('gemini-failed')
        ->and($outcomes[1]->result()->isFailure())->toBeTrue()
        ->and($http->requests[3]->url())->toBe('https://generativelanguage.googleapis.com/download/v1beta/files/gemini-output:download?alt=media');

    expect(fn () => iterator_to_array($batches->results($withoutManifest)->items()))->toThrow(RuntimeException::class);
});

it('rejects an untrusted Gemini upload URL before sending input bytes', function () {
    $http = new GeminiBatchScriptedHttpClient([
        HttpResponse::sync(200, ['X-Goog-Upload-URL' => 'https://example.test/steal'], ''),
    ]);
    $sender = new class () implements CanSendBatchFileBody {
        public int $calls = 0;
        public function send(BatchFileBodyRequest $request): BatchFileUploadResponse
        {
            $this->calls++;
            throw new LogicException('Must not send batch input to an untrusted upload URL.');
        }
    };
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig(geminiBatchConfig(), http: $http, bodySender: $sender));

    expect(fn () => $batches->submit(geminiBatchItems()))->toThrow(BatchSubmissionException::class)
        ->and($sender->calls)->toBe(0);
});
