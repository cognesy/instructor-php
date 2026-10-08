<?php

declare(strict_types=1);

require __DIR__.'/autoload.php';

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Preparation\BatchInputPreparer;
use Cognesy\Polyglot\BatchInference\Preparation\BatchRequestNormalizer;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

$producer = (static function (): iterable {
    for ($index = 0; $index < 50000; $index++) {
        yield BatchItem::of(
            'key-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
            new InferenceRequest(messages: Messages::fromString('x'))
        );
    }
})();
$encoder = new class () implements CanEncodeBatchItem {
    public function encode(BatchItem $item): array
    {
        return ['custom_id' => $item->key()];
    }
};
$input = (new BatchInputPreparer(
    new BatchRequestNormalizer('openai', 'gpt-batch-test'),
    $encoder,
    maxItems: 50000,
    maxBytes: 10000000,
))->prepare(BatchItems::fromIterable($producer));

try {
    echo json_encode([
        'count' => $input->count(),
        'bytes' => $input->bytes(),
        'peakBytes' => memory_get_peak_usage(true),
    ], JSON_THROW_ON_ERROR);
} finally {
    $input->close();
}
