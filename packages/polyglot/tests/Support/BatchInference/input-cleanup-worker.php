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

$preparer = new BatchInputPreparer(
    new BatchRequestNormalizer('openai', 'gpt-batch-test'),
    new class () implements CanEncodeBatchItem {
        public function encode(BatchItem $item): array
        {
            return ['custom_id' => $item->key()];
        }
    },
);
$item = BatchItem::of('same', new InferenceRequest(messages: Messages::fromString('test')));
$rejected = false;
try {
    $preparer->prepare(BatchItems::of($item, $item));
} catch (InvalidArgumentException) {
    $rejected = true;
}
$afterFailure = glob(sys_get_temp_dir().'/polyglot-batch-*') ?: [];
$prepared = $preparer->prepare(BatchItems::of($item));
$path = $prepared->path();
unset($prepared);

echo json_encode([
    'rejected' => $rejected,
    'afterFailure' => count($afterFailure),
    'afterAbandonment' => file_exists($path),
    'leftovers' => count(glob(sys_get_temp_dir().'/polyglot-batch-*') ?: []),
], JSON_THROW_ON_ERROR);
