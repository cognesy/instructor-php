<?php declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

require __DIR__.'/autoload.php';

final class ResumeWorkerHttp implements CanSendHttpRequests, CanHandleHttpRequest
{
    /** @var list<string> */
    public array $requests = [];

    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request->url();
        $base = dirname(__DIR__, 2).'/Fixtures/BatchInference/openai/';
        $fixture = match ($request->url()) {
            'https://api.openai.com/v1/batches/batch_test' => 'job-completed.json',
            'https://api.openai.com/v1/files/file-output/content' => 'output-chat.jsonl',
            'https://api.openai.com/v1/files/file-error/content' => 'error.jsonl',
            default => throw new RuntimeException('Unexpected batch HTTP request in resume worker.'),
        };
        $body = file_get_contents($base.$fixture);
        if ($body === false) {
            throw new RuntimeException('Missing batch fixture in resume worker.');
        }
        return str_ends_with($fixture, '.jsonl')
            ? HttpResponse::streamingFromIterable(200, [], str_split($body, 13))
            : HttpResponse::sync(200, [], $body);
    }
}

$input = stream_get_contents(STDIN);
$data = json_decode($input, true, flags: JSON_THROW_ON_ERROR);
if (!is_array($data)) {
    throw new RuntimeException('Resume worker needs a serialized reference.');
}
$reference = BatchReference::fromArray($data);
$config = BatchConfig::fromLLMConfig(new LLMConfig(
    apiUrl: 'https://api.openai.com/v1', apiKey: 'test-secret',
    endpoint: '/chat/completions', model: 'gpt-batch-test', driver: 'openai',
));
$http = new ResumeWorkerHttp();
$batches = BatchInference::fromRuntime(BatchRuntime::fromConfig($config, http: $http));
$job = $batches->retrieve($reference);
$items = [];
foreach ($batches->results($reference)->items() as $item) {
    $items[$item->key()] = $item->result()->isSuccess() ? 'success' : $item->result()->error()->kind()->value;
}
echo json_encode([
    'status' => $job->status()->value,
    'reference' => $job->reference()->toArray(),
    'items' => $items,
    'requests' => $http->requests,
], JSON_THROW_ON_ERROR);
