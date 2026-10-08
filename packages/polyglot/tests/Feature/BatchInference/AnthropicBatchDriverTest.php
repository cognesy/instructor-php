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
use Cognesy\Polyglot\BatchInference\Contracts\CanSendBatchFileBody;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileBodyRequest;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class AnthropicBatchScriptedHttpClient implements CanSendHttpRequests, CanHandleHttpRequest
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
        return array_shift($this->responses) ?? throw new LogicException('No scripted HTTP response remains.');
    }
}

function anthropicBatchFixture(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/anthropic/'.$name);
}

it('uses a streamed inline submission and preserves mixed Anthropic item outcomes', function () {
    $ended = anthropicBatchFixture('job-ended.json');
    $http = new AnthropicBatchScriptedHttpClient([
        HttpResponse::sync(200, [], '{"id":"msgbatch_test","processing_status":"canceling"}'),
        HttpResponse::sync(200, [], $ended),
        HttpResponse::sync(200, [], $ended),
        HttpResponse::streamingFromIterable(200, [], str_split(anthropicBatchFixture('results.jsonl'), 9)),
        HttpResponse::sync(200, [], anthropicBatchFixture('list.json')),
    ]);
    $sender = new class () implements CanSendBatchFileBody {
        public ?BatchFileBodyRequest $request = null;
        public array $body = [];

        public function send(BatchFileBodyRequest $request): BatchFileUploadResponse
        {
            $this->request = $request;
            $this->body = json_decode((string) file_get_contents($request->path()), true, flags: JSON_THROW_ON_ERROR);
            return new BatchFileUploadResponse(200, anthropicBatchFixture('job-submitted.json'));
        }
    };
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.anthropic.com/v1',
        apiKey: 'test-secret',
        endpoint: '/messages',
        metadata: ['apiVersion' => '2023-06-01', 'workspace' => 'workspace-a'],
        model: 'claude-test',
        driver: 'anthropic',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http, bodySender: $sender));
    $items = BatchItems::of(
        BatchItem::of('item-ok', new InferenceRequest(messages: Messages::fromString('A'))),
        BatchItem::of('item-expired', new InferenceRequest(messages: Messages::fromString('B'))),
    );

    $submitted = $batches->submit($items);
    $reference = $submitted->reference();
    $receipt = $batches->cancel($reference);
    $finished = $batches->retrieve($reference);
    $results = $batches->results($reference);
    $outcomes = iterator_to_array($results->items());
    $page = $batches->listJobs(limit: 2);

    expect($submitted->status())->toBe(BatchStatus::Running)
        ->and($reference->expectedCount())->toBe(2)
        ->and($receipt->providerStatus())->toBe('canceling')
        ->and($finished->status())->toBe(BatchStatus::Completed)
        ->and($finished->progress()->expired())->toBe(1)
        ->and($results->availability())->toBe(BatchResultsAvailability::Final)
        ->and($outcomes[0]->key())->toBe('item-ok')
        ->and($outcomes[0]->result()->unwrap()->message()->content()->toString())->toBe('Hi!')
        ->and($outcomes[1]->result()->error()->kind())->toBe(BatchItemFailureKind::Expired)
        ->and($sender->body['requests'][0]['custom_id'])->toBe('item-ok')
        ->and($sender->body['requests'][0]['params']['model'])->toBe('claude-test')
        ->and($sender->request->url())->toBe('https://api.anthropic.com/v1/messages/batches')
        ->and($page->nextCursor()?->token())->toBe('msgbatch_test')
        ->and(count($http->requests))->toBe(5)
        ->and($http->requests[3]->options()['stream'])->toBeTrue();
});

it('rejects an invalid Anthropic custom_id before network submission', function () {
    $http = new AnthropicBatchScriptedHttpClient([]);
    $sender = new class () implements CanSendBatchFileBody {
        public int $calls = 0;
        public function send(BatchFileBodyRequest $request): BatchFileUploadResponse
        {
            ++$this->calls;
            throw new LogicException('unexpected network call');
        }
    };
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.anthropic.com/v1',
        apiKey: 'test-secret',
        endpoint: '/messages',
        model: 'claude-test',
        driver: 'anthropic',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http, bodySender: $sender));

    expect(fn () => $batches->submit(BatchItems::of(BatchItem::of('bad/key', new InferenceRequest(messages: Messages::fromString('A'))))))
        ->toThrow(InvalidArgumentException::class, 'custom_id')
        ->and($sender->calls)->toBe(0)
        ->and($http->requests)->toBe([]);
});

it('keeps Anthropic cancellation as a job transition with mixed final item outcomes', function () {
    $ended = anthropicBatchFixture('job-ended-cancelled.json');
    $http = new AnthropicBatchScriptedHttpClient([
        HttpResponse::sync(200, [], '{"id":"msgbatch_cancelled","processing_status":"canceling"}'),
        HttpResponse::sync(200, [], $ended),
        HttpResponse::sync(200, [], $ended),
        HttpResponse::streamingFromIterable(200, [], [anthropicBatchFixture('results-cancelled.jsonl')]),
    ]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.anthropic.com/v1',
        apiKey: 'test-secret',
        endpoint: '/messages',
        model: 'claude-test',
        driver: 'anthropic',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(new BatchJobId('msgbatch_cancelled'), 'anthropic', $config->scope(), '/v1/messages', 'anthropic-messages', 2, true);

    $receipt = $batches->cancel($reference);
    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);
    $items = iterator_to_array($results->items());

    expect($receipt->providerStatus())->toBe('canceling')
        ->and($receipt->alreadyTerminal())->toBeFalse()
        ->and($job->status())->toBe(BatchStatus::Completed)
        ->and($job->progress()->cancelled())->toBe(1)
        ->and($results->availability())->toBe(BatchResultsAvailability::Final)
        ->and(count($items))->toBe(2)
        ->and($items[0]->result()->isSuccess())->toBeTrue()
        ->and($items[1]->key())->toBe('item-cancelled')
        ->and($items[1]->result()->error()->kind())->toBe(BatchItemFailureKind::Cancelled);
});

it('reports archived Anthropic results as unavailable after completed processing', function () {
    $archived = anthropicBatchFixture('job-ended-archived.json');
    $http = new AnthropicBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $archived),
        HttpResponse::sync(200, [], $archived),
    ]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.anthropic.com/v1',
        apiKey: 'test-secret',
        endpoint: '/messages',
        model: 'claude-test',
        driver: 'anthropic',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new BatchReference(new BatchJobId('msgbatch_archived'), 'anthropic', $config->scope(), '/v1/messages', 'anthropic-messages', 1, true);

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);

    expect($job->status())->toBe(BatchStatus::Completed)
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->availability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->isAvailable())->toBeFalse()
        ->and($results->unavailableReason())->not->toBeNull()
        ->and(count($http->requests))->toBe(2);
});
