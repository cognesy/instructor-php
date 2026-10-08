<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\FireworksBatchSettings;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Drivers\Fireworks\FireworksBatchDriver;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\BatchInference\Exceptions\UnsupportedBatchOperation;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;
use Cognesy\Polyglot\BatchInference\Transport\BatchHttpTransport;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class FireworksScriptedHttp implements CanSendHttpRequests, CanHandleHttpRequest
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    public bool $rejectJobCreation = false;
    public bool $loseJobAcknowledgement = false;
    public bool $jobFailsAfterSubmission = false;
    public bool $emptyFirstPage = false;
    private ?string $outputDatasetId = null;

    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $url = $request->url();
        if ($request->method() === 'POST' && str_ends_with($url, '/datasets')) {
            $body = json_decode($request->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
            return $this->json(200, ['name' => 'accounts/acct-test/datasets/' . $body['datasetId']]);
        }
        if ($request->method() === 'POST' && str_contains($url, '/batchInferenceJobs?')) {
            $body = json_decode($request->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
            $this->outputDatasetId = $body['outputDatasetId'] ?? null;
            if ($this->rejectJobCreation) {
                return $this->json(400, ['message' => 'payment method required']);
            }
            if ($this->loseJobAcknowledgement) {
                throw new NetworkException('Connection lost after job creation was sent.');
            }
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            return $this->json(200, [
                'name' => 'accounts/acct-test/batchInferenceJobs/' . $query['batchInferenceJobId'],
                'state' => 'JOB_STATE_VALIDATING',
                'outputDatasetId' => $this->outputDatasetId,
            ]);
        }
        if ($request->method() === 'GET' && str_contains($url, '/batchInferenceJobs?')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            if ($this->emptyFirstPage) {
                return $this->json(200, ($query['pageToken'] ?? null) === 'page-2'
                    ? ['batchInferenceJobs' => [['name' => 'accounts/acct-test/batchInferenceJobs/existing', 'state' => 'JOB_STATE_RUNNING']]]
                    : ['batchInferenceJobs' => [], 'nextPageToken' => 'page-2']);
            }
            return $this->json(200, ($query['pageToken'] ?? null) === 'page-2'
                ? ['batchInferenceJobs' => []]
                : [
                    'batchInferenceJobs' => [['name' => 'accounts/acct-test/batchInferenceJobs/existing', 'state' => 'JOB_STATE_RUNNING']],
                    'nextPageToken' => 'page-2',
                ]);
        }
        if ($request->method() === 'GET' && str_contains($url, '/batchInferenceJobs/')) {
            return $this->json(200, [
                'name' => substr($url, strpos($url, 'accounts/')),
                'state' => $this->jobFailsAfterSubmission ? 'JOB_STATE_FAILED' : 'JOB_STATE_COMPLETED',
                'status' => $this->jobFailsAfterSubmission ? ['code' => 'INVALID_ARGUMENT', 'message' => 'Input validation failed.'] : ['code' => 'OK'],
                'jobProgress' => ['totalInputRequests' => 2, 'successfullyProcessedRequests' => 1, 'failedRequests' => 1],
                'outputDatasetId' => $this->outputDatasetId ?? 'accounts/acct-test/datasets/output',
            ]);
        }
        throw new LogicException('Unexpected Fireworks request: ' . $request->method());
    }

    /** @param array<string, mixed> $body */
    private function json(int $status, array $body): HttpResponse
    {
        return HttpResponse::sync($status, [], json_encode($body, JSON_THROW_ON_ERROR));
    }
}

final class FireworksScriptedUpload implements CanUploadBatchFile
{
    public ?BatchFileUpload $request = null;
    public string $body = '';
    public bool $reject = false;

    public function upload(BatchFileUpload $request): BatchFileUploadResponse
    {
        $this->request = $request;
        $this->body = (string) file_get_contents($request->path());
        if ($this->reject) {
            throw new BatchSubmissionException('Upload rejected.', 'upload', BatchMutationCertainty::Rejected);
        }
        return new BatchFileUploadResponse(200, '{}');
    }
}

function fireworksBatchClient(FireworksScriptedHttp $http, FireworksScriptedUpload $upload): BatchInference
{
    $config = new LLMConfig(
        apiUrl: 'https://api.fireworks.ai/inference/v1',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'accounts/fireworks/models/llama-v3p1-8b-instruct',
        maxTokens: 40,
        driver: 'fireworks',
    );
    $driver = new FireworksBatchDriver($config, new FireworksBatchSettings('acct-test'), new BatchHttpTransport($http), $upload);
    return BatchInference::fromRuntime(new BatchRuntime($driver));
}

function fireworksBatchItems(): BatchItems
{
    return BatchItems::of(
        BatchItem::of('first', new InferenceRequest(messages: Messages::fromString('One'))),
        BatchItem::of('second', new InferenceRequest(messages: Messages::fromString('Two'))),
    );
}

it('keeps Fireworks dataset and job identities across submit, retrieve and paginated list', function () {
    $http = new FireworksScriptedHttp();
    $upload = new FireworksScriptedUpload();
    $batches = fireworksBatchClient($http, $upload);

    $submitted = $batches->submit(fireworksBatchItems());
    $reference = BatchReference::fromArray($submitted->reference()->toArray());
    $finished = $batches->retrieve($reference);
    $firstPage = $batches->listJobs(limit: 2);
    $secondPage = $batches->listJobs(limit: 2, cursor: $firstPage->nextCursor());

    $inputRow = json_decode(explode("\n", $upload->body)[0], true, flags: JSON_THROW_ON_ERROR);
    $datasetRequest = json_decode($http->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
    $createRequest = json_decode($http->requests[1]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
    expect($submitted->status())->toBe(BatchStatus::Pending)
        ->and($reference->expectedCount())->toBe(2)
        ->and($finished->status())->toBe(BatchStatus::Completed)
        ->and($finished->resultsAvailability())->toBe(BatchResultsAvailability::Unsupported)
        ->and($finished->progress()->completed())->toBe(1)
        ->and($finished->progress()->failed())->toBe(1)
        ->and($batches->capabilities()->canCancel())->toBeFalse()
        ->and($batches->capabilities()->canReadResults())->toBeFalse()
        ->and($inputRow['custom_id'])->toBe('first')
        ->and($inputRow['body']['max_tokens'])->toBe(40)
        ->and(array_key_exists('model', $inputRow['body']))->toBeFalse()
        ->and($datasetRequest['dataset']['exampleCount'])->toBe('2')
        ->and($datasetRequest['dataset']['userUploaded'])->toBe([])
        ->and($http->requests[0]->body()->toString())->toContain('"userUploaded":{}')
        ->and($createRequest['model'])->toBe('accounts/fireworks/models/llama-v3p1-8b-instruct')
        ->and($createRequest['inputDatasetId'])->toBe('accounts/acct-test/datasets/' . $datasetRequest['datasetId'])
        ->and($createRequest['outputDatasetId'])->toBe('accounts/acct-test/datasets/' . str_replace('-input', '-output', $datasetRequest['datasetId']))
        ->and($upload->request->url())->toBe('https://api.fireworks.ai/v1/accounts/acct-test/datasets/' . $datasetRequest['datasetId'] . ':upload')
        ->and(file_exists($upload->request->path()))->toBeFalse()
        ->and($firstPage->nextCursor()?->token())->toBe('page-2')
        ->and(count(iterator_to_array($firstPage->jobs())))->toBe(1)
        ->and(count(iterator_to_array($secondPage->jobs())))->toBe(0)
        ->and($http->requests[4]->url())->toContain('pageSize=2', 'pageToken=page-2');

    $requestCount = count($http->requests);
    expect(fn () => $batches->cancel($reference))->toThrow(UnsupportedBatchOperation::class);
    expect(fn () => $batches->results($reference))->toThrow(UnsupportedBatchOperation::class);
    expect(count($http->requests))->toBe($requestCount);
});

it('keeps Fireworks listing continuation after an empty page', function () {
    $http = new FireworksScriptedHttp();
    $http->emptyFirstPage = true;
    $batches = fireworksBatchClient($http, new FireworksScriptedUpload());

    $first = $batches->listJobs(limit: 2);
    $second = $batches->listJobs(limit: 2, cursor: $first->nextCursor());

    expect(iterator_to_array($first->jobs()))->toBe([])
        ->and($first->nextCursor()?->token())->toBe('page-2')
        ->and(count(iterator_to_array($second->jobs())))->toBe(1)
        ->and($second->nextCursor())->toBeNull();
});

it('does not treat a Fireworks job acknowledgement as completed inference', function () {
    $http = new FireworksScriptedHttp();
    $http->jobFailsAfterSubmission = true;
    $batches = fireworksBatchClient($http, new FireworksScriptedUpload());

    $submitted = $batches->submit(fireworksBatchItems());
    $failed = $batches->retrieve($submitted->reference());

    expect($submitted->status())->toBe(BatchStatus::Pending)
        ->and($failed->status())->toBe(BatchStatus::Failed)
        ->and($failed->providerStatus())->toBe('JOB_STATE_FAILED')
        ->and($failed->failureCode())->toBe('INVALID_ARGUMENT')
        ->and($failed->failureMessage())->toBe('Input validation failed.');
});

it('rejects mixed Fireworks models before creating a dataset', function () {
    $http = new FireworksScriptedHttp();
    $upload = new FireworksScriptedUpload();
    $batches = fireworksBatchClient($http, $upload);
    $item = BatchItem::of('different-model', new InferenceRequest(
        messages: Messages::fromString('One'),
        model: 'accounts/fireworks/models/other-model',
    ));

    expect(fn () => $batches->submit(BatchItems::of($item)))->toThrow(InvalidArgumentException::class, 'job-level model')
        ->and($http->requests)->toBe([])
        ->and($upload->request)->toBeNull();
});

it('retains the created dataset identity when Fireworks rejects its upload', function () {
    $http = new FireworksScriptedHttp();
    $upload = new FireworksScriptedUpload();
    $upload->reject = true;

    try {
        fireworksBatchClient($http, $upload)->submit(fireworksBatchItems());
        test()->fail('Expected Fireworks dataset upload to be rejected.');
    } catch (BatchSubmissionException $error) {
        expect($error->stage())->toBe('upload')
            ->and($error->certainty())->toBe(BatchMutationCertainty::Rejected)
            ->and($error->artifactIds()['input_dataset'] ?? null)->toStartWith('accounts/acct-test/datasets/')
            ->and(file_exists($upload->request->path()))->toBeFalse()
            ->and(count($http->requests))->toBe(1);
    }
});

it('preserves a recoverable Fireworks job name when its creation acknowledgement is lost', function () {
    $http = new FireworksScriptedHttp();
    $http->loseJobAcknowledgement = true;
    $upload = new FireworksScriptedUpload();

    try {
        fireworksBatchClient($http, $upload)->submit(fireworksBatchItems());
        test()->fail('Expected an uncertain Fireworks job creation.');
    } catch (BatchSubmissionException $error) {
        $createRequest = json_decode($http->requests[1]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
        expect($error->stage())->toBe('create')
            ->and($error->certainty())->toBe(BatchMutationCertainty::MayHaveSucceeded)
            ->and($error->artifactIds()['input_dataset'] ?? null)->toStartWith('accounts/acct-test/datasets/')
            ->and($error->artifactIds()['output_dataset'] ?? null)->toBe($createRequest['outputDatasetId'])
            ->and($error->artifactIds()['job_name'] ?? null)->toStartWith('accounts/acct-test/batchInferenceJobs/')
            ->and(file_exists($upload->request->path()))->toBeFalse()
            ->and(count($http->requests))->toBe(2);
    }
});

it('rejects forged Fireworks job names before issuing a status request', function () {
    $http = new FireworksScriptedHttp();
    $batches = fireworksBatchClient($http, new FireworksScriptedUpload());
    $reference = new BatchReference(
        new BatchJobId('accounts/acct-test/batchInferenceJobs/other?readMask=*'),
        'fireworks',
        'fireworks|https://api.fireworks.ai/v1|acct-test',
        '/v1/chat/completions',
        'fireworks-unqualified',
    );

    expect(fn () => $batches->retrieve($reference))->toThrow(BatchException::class);
    expect($http->requests)->toBe([]);
});

it('rejects incompatible Fireworks endpoint and codec references before HTTP', function () {
    $http = new FireworksScriptedHttp();
    $batches = fireworksBatchClient($http, new FireworksScriptedUpload());
    $base = new BatchReference(
        new BatchJobId('accounts/acct-test/batchInferenceJobs/polyglot-test'),
        'fireworks',
        'fireworks|https://api.fireworks.ai/v1|acct-test',
        '/v1/chat/completions',
        'fireworks-unqualified',
    );
    $wrongRoute = $base->toArray();
    $wrongRoute['route'] = '/v1/embeddings';
    $wrongCodec = $base->toArray();
    $wrongCodec['codec'] = 'openai-chat';

    expect(fn () => $batches->retrieve(BatchReference::fromArray($wrongRoute)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $batches->results(BatchReference::fromArray($wrongCodec)))->toThrow(InvalidArgumentException::class)
        ->and($http->requests)->toBe([]);
});

it('preserves the Fireworks input dataset and proposed job ID after a rejected job creation', function () {
    $http = new FireworksScriptedHttp();
    $http->rejectJobCreation = true;
    $upload = new FireworksScriptedUpload();
    $batches = fireworksBatchClient($http, $upload);

    try {
        $batches->submit(fireworksBatchItems());
        test()->fail('Expected Fireworks job creation to be rejected.');
    } catch (BatchSubmissionException $error) {
        $createRequest = json_decode($http->requests[1]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
        expect($error->stage())->toBe('create')
            ->and($error->certainty())->toBe(BatchMutationCertainty::Rejected)
            ->and($error->artifactIds()['input_dataset'] ?? null)->toStartWith('accounts/acct-test/datasets/')
            ->and($error->artifactIds()['output_dataset'] ?? null)->toBe($createRequest['outputDatasetId'])
            ->and($error->artifactIds()['job_name'] ?? null)->toStartWith('accounts/acct-test/batchInferenceJobs/');
    }
});
