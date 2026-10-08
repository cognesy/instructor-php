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
use Cognesy\Polyglot\BatchInference\Config\GroqBatchOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class GroqBatchScriptedHttpClient implements CanSendHttpRequests, CanHandleHttpRequest
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
        return array_shift($this->responses) ?? throw new LogicException('No scripted Groq response remains.');
    }
}

function groqBatchFixture(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/groq/'.$name);
}

it('submits, observes, cancels, reads mixed outcomes and paginates Groq batches', function () {
    $completed = groqBatchFixture('job-completed.json');
    $http = new GroqBatchScriptedHttpClient([
        HttpResponse::sync(200, [], groqBatchFixture('job-submitted.json')),
        HttpResponse::sync(200, [], '{"id":"batch_groq_test","endpoint":"/v1/chat/completions","status":"cancelling"}'),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::streamingFromIterable(200, [], str_split(groqBatchFixture('output.jsonl'), 17)),
        HttpResponse::streamingFromIterable(200, [], str_split(groqBatchFixture('error.jsonl'), 9)),
        HttpResponse::sync(200, [], groqBatchFixture('list.json')),
        HttpResponse::sync(200, [], '{"object":"list","data":[],"paging":{"next_cursor":null}}'),
    ]);
    $uploader = new class () implements CanUploadBatchFile {
        public string $body = '';
        public ?BatchFileUpload $request = null;

        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            $this->request = $request;
            $this->body = (string) file_get_contents($request->path());
            return new BatchFileUploadResponse(200, '{"id":"file_groq_input"}');
        }
    };
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.groq.com/openai/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'llama-3.1-8b-instant',
        driver: 'groq',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http, uploader: $uploader));
    $items = BatchItems::of(
        BatchItem::of('groq-ok', new InferenceRequest(messages: Messages::fromString('A'))),
        BatchItem::of('groq-failed', new InferenceRequest(messages: Messages::fromString('B'), model: 'llama-3.3-70b-versatile')),
    );

    $submitted = $batches->submit($items, new GroqBatchOptions('2d'));
    $reference = BatchReference::fromArray($submitted->reference()->toArray());
    $receipt = $batches->cancel($reference);
    $finished = $batches->retrieve($reference);
    $results = $batches->results($reference);
    $outcomes = iterator_to_array($results->items());
    $page = $batches->listJobs(limit: 2);
    $nextPage = $batches->listJobs(limit: 2, cursor: $page->nextCursor());

    $lines = array_values(array_filter(explode("\n", $uploader->body)));
    $firstInput = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
    $secondInput = json_decode($lines[1], true, flags: JSON_THROW_ON_ERROR);
    $createBody = json_decode($http->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
    expect($submitted->status())->toBe(BatchStatus::Pending)
        ->and($reference->expectedCount())->toBe(2)
        ->and($receipt->providerStatus())->toBe('cancelling')
        ->and($finished->status())->toBe(BatchStatus::Completed)
        ->and($results->availability())->toBe(BatchResultsAvailability::Final)
        ->and($outcomes[0]->key())->toBe('groq-ok')
        ->and($outcomes[0]->result()->unwrap()->message()->content()->toString())->toBe('Hello from Groq')
        ->and($outcomes[1]->key())->toBe('groq-failed')
        ->and($outcomes[1]->result()->isFailure())->toBeTrue()
        ->and($firstInput['url'])->toBe('/v1/chat/completions')
        ->and($firstInput['body']['model'])->toBe('llama-3.1-8b-instant')
        ->and($secondInput['body']['model'])->toBe('llama-3.3-70b-versatile')
        ->and($uploader->request->url())->toBe('https://api.groq.com/openai/v1/files')
        ->and($createBody['completion_window'])->toBe('2d')
        ->and($page->nextCursor()?->token())->toBe('cursor_next_groq')
        ->and($nextPage->nextCursor())->toBeNull()
        ->and($http->requests[6]->url())->toBe('https://api.groq.com/openai/v1/batches')
        ->and($http->requests[7]->url())->toBe('https://api.groq.com/openai/v1/batches?cursor=cursor_next_groq');
});

it('rejects a Groq chat reference with a mismatched endpoint before HTTP', function () {
    $http = new GroqBatchScriptedHttpClient([]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.groq.com/openai/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'llama-3.1-8b-instant',
        driver: 'groq',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_other'),
        'groq',
        $config->scope(),
        '/v1/audio/transcriptions',
        'groq-chat',
    );

    expect(fn () => $batches->results($reference))->toThrow(InvalidArgumentException::class)
        ->and($http->requests)->toBe([]);
});

it('retains Groq output and error artifacts when a batch expires', function () {
    $expired = groqBatchFixture('job-expired-artifacts.json');
    $http = new GroqBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $expired),
        HttpResponse::sync(200, [], $expired),
        HttpResponse::streamingFromIterable(200, [], [groqBatchFixture('output.jsonl')]),
        HttpResponse::streamingFromIterable(200, [], [groqBatchFixture('error.jsonl')]),
    ]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.groq.com/openai/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'llama-3.1-8b-instant',
        driver: 'groq',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_groq_expired'),
        'groq',
        $config->scope(),
        '/v1/chat/completions',
        'groq-chat',
    );

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);
    $items = iterator_to_array($results->items());

    expect($job->status())->toBe(BatchStatus::Expired)
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Final)
        ->and(count($items))->toBe(2)
        ->and($items[0]->key())->toBe('groq-ok')
        ->and($items[0]->result()->isSuccess())->toBeTrue()
        ->and($items[1]->key())->toBe('groq-failed')
        ->and($items[1]->result()->isFailure())->toBeTrue();
});

it('preserves Groq validation diagnostics from a top-level errors list', function () {
    $failed = groqBatchFixture('job-failed-validation.json');
    $http = new GroqBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $failed),
        HttpResponse::sync(200, [], $failed),
    ]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.groq.com/openai/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'llama-3.1-8b-instant',
        driver: 'groq',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_groq_failed'),
        'groq',
        $config->scope(),
        '/v1/chat/completions',
        'groq-chat',
    );

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);

    expect($job->status())->toBe(BatchStatus::Failed)
        ->and($job->failureCode())->toBe('invalid_method')
        ->and($job->failureMessage())->toBe('Invalid value for method.')
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->availability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($http->requests)->toHaveCount(2);
});
