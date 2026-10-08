<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchCancellationException;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class OpenAIBatchScriptedHttpClient implements CanSendHttpRequests, CanHandleHttpRequest
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

function openAIBatchFixture(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/BatchInference/openai/'.$name);
}

it('submits, observes, cancels, reads mixed outcomes, and lists OpenAI batches', function () {
    $completed = openAIBatchFixture('job-completed.json');
    $http = new OpenAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], openAIBatchFixture('job-submitted.json')),
        HttpResponse::sync(200, [], '{"id":"batch_test","status":"cancelling"}'),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::sync(200, [], $completed),
        HttpResponse::streamingFromIterable(200, [], str_split(openAIBatchFixture('output-chat.jsonl'), 13)),
        HttpResponse::streamingFromIterable(200, [], str_split(openAIBatchFixture('error.jsonl'), 7)),
        HttpResponse::sync(200, [], openAIBatchFixture('list.json')),
    ]);
    $uploader = new class () implements CanUploadBatchFile {
        public string $body = '';
        public ?BatchFileUpload $request = null;

        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            $this->request = $request;
            $this->body = (string) file_get_contents($request->path());
            return new BatchFileUploadResponse(200, '{"id":"file-input"}');
        }
    };
    $inference = new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'gpt-batch-test',
        driver: 'openai',
    );
    $runtime = BatchRuntime::fromConfig(BatchConfig::fromLLMConfig($inference), http: $http, uploader: $uploader);
    $batches = BatchInference::fromRuntime($runtime);
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

    $lines = array_filter(explode("\n", $uploader->body));
    $firstInput = json_decode(reset($lines), true, flags: JSON_THROW_ON_ERROR);
    expect($submitted->status())->toBe(BatchStatus::Pending)
        ->and($reference->expectedCount())->toBe(2)
        ->and($reference->inputClosed())->toBeTrue()
        ->and($receipt->providerStatus())->toBe('cancelling')
        ->and($receipt->alreadyTerminal())->toBeFalse()
        ->and($finished->status())->toBe(BatchStatus::Completed)
        ->and($finished->progress()->failed())->toBe(1)
        ->and($results->availability())->toBe(BatchResultsAvailability::Final)
        ->and($outcomes[0]->key())->toBe('item-ok')
        ->and($outcomes[0]->result()->unwrap()->message()->content()->toString())->toBe('Hello!')
        ->and($outcomes[1]->key())->toBe('item-expired')
        ->and($outcomes[1]->result()->error()->kind())->toBe(BatchItemFailureKind::Expired)
        ->and($firstInput['custom_id'])->toBe('item-ok')
        ->and($firstInput['url'])->toBe('/v1/chat/completions')
        ->and($firstInput['body']['model'])->toBe('gpt-batch-test')
        ->and($uploader->request->fields()['purpose'])->toBe('batch')
        ->and($page->nextCursor()?->token())->toBe('batch_test')
        ->and(count($http->requests))->toBe(7)
        ->and($http->requests[0]->method())->toBe('POST')
        ->and($http->requests[0]->url())->toBe('https://api.openai.com/v1/batches')
        ->and($http->requests[4]->options()['stream'])->toBeTrue();
});

it('uses the Responses wire codec and recovers after a creation acknowledgement is lost', function () {
    $responsesJob = '{"id":"batch_responses","status":"completed","endpoint":"/v1/responses","request_counts":{"total":1,"completed":1,"failed":0},"output_file_id":"file-responses"}';
    $http = new OpenAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], '{"id":"batch_responses","status":"validating","endpoint":"/v1/responses"}'),
        HttpResponse::sync(200, [], $responsesJob),
        HttpResponse::streamingFromIterable(200, [], str_split(openAIBatchFixture('output-responses.jsonl'), 11)),
    ]);
    $uploader = new class () implements CanUploadBatchFile {
        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            return new BatchFileUploadResponse(200, '{"id":"file-responses-input"}');
        }
    };
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'test-secret',
        endpoint: '/responses',
        model: 'gpt-batch-test',
        driver: 'openai-responses',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http, uploader: $uploader));
    $submitted = $batches->submit(BatchItems::of(BatchItem::of('response-item', new InferenceRequest(messages: Messages::fromString('Hello')))));
    $resumed = \Cognesy\Polyglot\BatchInference\Data\BatchReference::fromArray($submitted->reference()->toArray());
    $result = iterator_to_array($batches->results($resumed)->items())[0];

    expect($result->result()->unwrap()->message()->content()->toString())->toBe('Response API output')
        ->and($result->provenance()->artifactId())->toBe('file-responses')
        ->and($submitted->reference()->codec())->toBe('openai-responses')
        ->and(json_decode($http->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR)['endpoint'])->toBe('/v1/responses');

    $lostAcknowledgement = new OpenAIBatchScriptedHttpClient([
        HttpResponse::sync(503, [], '{"error":"unavailable"}'),
    ]);
    $retryGuard = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $lostAcknowledgement, uploader: $uploader));
    try {
        $retryGuard->submit(BatchItems::of(BatchItem::of('one', new InferenceRequest(messages: Messages::fromString('Hello')))));
        test()->fail('Expected a failed batch creation.');
    } catch (BatchSubmissionException $error) {
        expect($error->certainty())->toBe(BatchMutationCertainty::MayHaveSucceeded)
            ->and($error->artifactIds())->toBe(['input_file_id' => 'file-responses-input'])
            ->and(count($lostAcknowledgement->requests))->toBe(1);
    }
});

it('preserves unkeyed cancellation errors without inventing caller keys', function () {
    $cancelled = '{"id":"batch_cancelled","status":"cancelled","endpoint":"/v1/chat/completions","error_file_id":"file-cancelled","request_counts":{"total":2,"completed":0,"failed":0}}';
    $http = new OpenAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $cancelled),
        HttpResponse::streamingFromIterable(200, [], ["{\"id\":\"batch_req_1\",\"custom_id\":null,\"response\":null,\"error\":{\"code\":\"batch_cancelled\",\"message\":\"Batch was cancelled.\"}}\n"]),
    ]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'gpt-batch-test',
        driver: 'openai',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new \Cognesy\Polyglot\BatchInference\Data\BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_cancelled'),
        'openai',
        $config->scope(),
        '/v1/chat/completions',
        'openai-chat',
        2,
        true,
    );
    $result = iterator_to_array($batches->results($reference)->items())[0];

    expect($result->key())->toBeNull()
        ->and($result->result()->error()->kind())->toBe(BatchItemFailureKind::Cancelled)
        ->and($result->provenance()->nativeRecordId())->toBe('batch_req_1');
});

it('reports a later OpenAI validation failure as job failure after accepted submission', function () {
    $failed = openAIBatchFixture('job-validation-failed.json');
    $http = new OpenAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], openAIBatchFixture('job-submitted.json')),
        HttpResponse::sync(200, [], $failed),
        HttpResponse::sync(200, [], $failed),
    ]);
    $uploader = new class () implements CanUploadBatchFile {
        public function upload(BatchFileUpload $request): BatchFileUploadResponse
        {
            return new BatchFileUploadResponse(200, '{"id":"file-input"}');
        }
    };
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'gpt-batch-test',
        driver: 'openai',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http, uploader: $uploader));
    $submitted = $batches->submit(BatchItems::of(BatchItem::of('item', new InferenceRequest(messages: Messages::fromString('A')))));
    $job = $batches->retrieve($submitted->reference());
    $results = $batches->results($submitted->reference());

    expect($submitted->status())->toBe(BatchStatus::Pending)
        ->and($job->status())->toBe(BatchStatus::Failed)
        ->and($job->failureCode())->toBe('invalid_json_line')
        ->and($job->failureMessage())->toBe('Input line failed validation.')
        ->and($results->availability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->unavailableReason())->not->toBeNull()
        ->and(count($http->requests))->toBe(3);
});

it('keeps expired OpenAI output and error artifacts available as final results', function () {
    $expired = openAIBatchFixture('job-expired-artifacts.json');
    $http = new OpenAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $expired),
        HttpResponse::sync(200, [], $expired),
        HttpResponse::streamingFromIterable(200, [], [openAIBatchFixture('output-chat.jsonl')]),
        HttpResponse::streamingFromIterable(200, [], [openAIBatchFixture('error.jsonl')]),
    ]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'gpt-batch-test',
        driver: 'openai',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new \Cognesy\Polyglot\BatchInference\Data\BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_expired_with_artifacts'),
        'openai',
        $config->scope(),
        '/v1/chat/completions',
        'openai-chat',
    );

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);
    $items = iterator_to_array($results->items());

    expect($job->status())->toBe(BatchStatus::Expired)
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Final)
        ->and($results->availability())->toBe(BatchResultsAvailability::Final)
        ->and(count($items))->toBe(2)
        ->and($items[0]->key())->toBe('item-ok')
        ->and($items[0]->result()->isSuccess())->toBeTrue()
        ->and($items[1]->key())->toBe('item-expired')
        ->and($items[1]->result()->error()->kind())->toBe(BatchItemFailureKind::Expired);
});

it('reports an expired OpenAI job without retained artifacts as unavailable', function () {
    $expired = openAIBatchFixture('job-expired-no-artifacts.json');
    $http = new OpenAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], $expired),
        HttpResponse::sync(200, [], $expired),
    ]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'gpt-batch-test',
        driver: 'openai',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new \Cognesy\Polyglot\BatchInference\Data\BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_expired_without_artifacts'),
        'openai',
        $config->scope(),
        '/v1/chat/completions',
        'openai-chat',
    );

    $job = $batches->retrieve($reference);
    $results = $batches->results($reference);

    expect($job->status())->toBe(BatchStatus::Expired)
        ->and($job->resultsAvailability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->availability())->toBe(BatchResultsAvailability::Unavailable)
        ->and($results->isAvailable())->toBeFalse()
        ->and($results->unavailableReason())->not->toBeNull()
        ->and(count($http->requests))->toBe(2);
});

it('keeps the job reference and uncertainty when a cancellation acknowledgement is lost', function () {
    $http = new class () implements CanSendHttpRequests, CanHandleHttpRequest {
        public int $calls = 0;

        public function send(HttpRequest $request): PendingHttpResponse
        {
            return new PendingHttpResponse($request, $this);
        }

        public function handle(HttpRequest $request): HttpResponse
        {
            $this->calls++;
            throw new NetworkException('Connection dropped after cancellation request.', $request);
        }
    };
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'gpt-batch-test',
        driver: 'openai',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new \Cognesy\Polyglot\BatchInference\Data\BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_cancel_test'),
        'openai',
        $config->scope(),
        '/v1/chat/completions',
        'openai-chat',
    );

    try {
        $batches->cancel($reference);
        test()->fail('Expected an uncertain cancellation acknowledgement.');
    } catch (BatchCancellationException $error) {
        expect($error->reference())->toBe($reference)
            ->and($error->certainty())->toBe(BatchMutationCertainty::MayHaveSucceeded)
            ->and($http->calls)->toBe(1);
    }

    $rejectedHttp = new OpenAIBatchScriptedHttpClient([
        HttpResponse::sync(400, [], '{"error":"invalid_request"}'),
    ]);
    $rejected = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $rejectedHttp));
    try {
        $rejected->cancel($reference);
        test()->fail('Expected a rejected cancellation request.');
    } catch (BatchCancellationException $error) {
        expect($error->certainty())->toBe(BatchMutationCertainty::Rejected)
            ->and(count($rejectedHttp->requests))->toBe(1);
    }

    $timeoutHttp = new OpenAIBatchScriptedHttpClient([
        HttpResponse::sync(408, [], '{"error":"timeout"}'),
    ]);
    $timeout = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $timeoutHttp));
    try {
        $timeout->cancel($reference);
        test()->fail('Expected an uncertain timeout response.');
    } catch (BatchCancellationException $error) {
        expect($error->certainty())->toBe(BatchMutationCertainty::MayHaveSucceeded)
            ->and(count($timeoutHttp->requests))->toBe(1);
    }
});

it('reports a completion race from a cancellation response without claiming cancellation', function () {
    $http = new OpenAIBatchScriptedHttpClient([
        HttpResponse::sync(200, [], '{"id":"batch_completed","status":"completed"}'),
    ]);
    $config = BatchConfig::fromLLMConfig(new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'gpt-batch-test',
        driver: 'openai',
    ));
    $batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
    $reference = new \Cognesy\Polyglot\BatchInference\Data\BatchReference(
        new \Cognesy\Polyglot\BatchInference\Data\BatchJobId('batch_completed'),
        'openai',
        $config->scope(),
        '/v1/chat/completions',
        'openai-chat',
    );

    $receipt = $batches->cancel($reference);

    expect($receipt->acknowledged())->toBeTrue()
        ->and($receipt->alreadyTerminal())->toBeTrue()
        ->and($receipt->providerStatus())->toBe('completed');
});
