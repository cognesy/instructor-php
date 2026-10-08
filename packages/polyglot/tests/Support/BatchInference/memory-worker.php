<?php declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Preparation\BatchInputPreparer;
use Cognesy\Polyglot\BatchInference\Preparation\BatchRequestNormalizer;
use Cognesy\Polyglot\BatchInference\Results\JsonlRecordReader;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchHttpTransport;
use Cognesy\Polyglot\BatchInference\Transport\CurlBatchFileUploader;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

require __DIR__.'/autoload.php';

final class MemoryProofHttp implements CanSendHttpRequests, CanHandleHttpRequest
{
    public function __construct(private string $path) {}

    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        if ($request->method() !== 'GET' || $request->url() !== 'https://example.test/output') {
            throw new RuntimeException('Unexpected memory proof download request.');
        }
        return HttpResponse::streamingFromIterable(200, [], $this->chunks());
    }

    /** @return iterable<string> */
    private function chunks(): iterable
    {
        $file = fopen($this->path, 'rb');
        if ($file === false) {
            throw new RuntimeException('Cannot open memory proof output.');
        }
        try {
            while (!feof($file)) {
                $chunk = fread($file, 65536);
                if ($chunk === false) {
                    throw new RuntimeException('Cannot read memory proof output.');
                }
                if ($chunk !== '') {
                    yield $chunk;
                }
            }
        } finally {
            fclose($file);
        }
    }
}

$url = $argv[1] ?? null;
if (!is_string($url)) {
    throw new InvalidArgumentException('Memory worker needs a local upload URL.');
}
$producer = (static function (): iterable {
    for ($index = 0; $index < 42; $index++) {
        yield BatchItem::of('row-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            new InferenceRequest(messages: Messages::fromString('memory proof')));
    }
})();
$encoder = new class implements CanEncodeBatchItem {
    public function encode(BatchItem $item): array
    {
        return ['custom_id' => $item->key(), 'body' => ['text' => str_repeat('x', 5000000)]];
    }
};
$input = (new BatchInputPreparer(
    new BatchRequestNormalizer('openai', 'gpt-batch-test'),
    $encoder,
    maxItems: 50,
    maxBytes: 230000000,
    maxRecordBytes: 6000000,
))->prepare(BatchItems::fromIterable($producer));

try {
    $hash = hash_file('sha256', $input->path());
    $uploaded = (new CurlBatchFileUploader())->upload(new BatchFileUpload(
        $url, $input->path(), ['Authorization' => 'Bearer local-test'], ['purpose' => 'batch'],
    ));
    $remote = json_decode($uploaded->body(), true, flags: JSON_THROW_ON_ERROR);
    $http = new BatchHttpTransport(new MemoryProofHttp($input->path()));
    $records = (new JsonlRecordReader(maxRecordBytes: 6000000))->records($http->stream('https://example.test/output', []));
    $count = 0;
    foreach ($records as $record) {
        if (($record['custom_id'] ?? null) !== 'row-'.str_pad((string) $count, 3, '0', STR_PAD_LEFT)
            || strlen((string) ($record['body']['text'] ?? '')) !== 5000000) {
            throw new RuntimeException('Large streamed result lost a record or key.');
        }
        $count++;
    }
    echo json_encode([
        'bytes' => $input->bytes(),
        'count' => $count,
        'localHash' => $hash,
        'remoteHash' => $remote['sha256'] ?? null,
        'remoteBytes' => $remote['bytes'] ?? null,
        'peakBytes' => memory_get_peak_usage(true),
    ], JSON_THROW_ON_ERROR);
} finally {
    $input->close();
}
