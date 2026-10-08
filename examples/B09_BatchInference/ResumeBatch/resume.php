<?php declare(strict_types=1);

require dirname(__DIR__, 2).'/boot.php';
require __DIR__.'/../Support/DemoBatch.php';

use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Examples\BatchInference\Support\DemoBatch;

$data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($data)) {
    throw new RuntimeException('A serialized batch reference is required.');
}
$reference = BatchReference::fromArray($data);
$batches = DemoBatch::client();
$job = $batches->retrieve($reference);
$keys = [];
foreach ($batches->results($reference)->items() as $item) {
    $keys[] = $item->key();
}
echo json_encode([
    'id' => $reference->id()->toString(),
    'status' => $job->status()->value,
    'keys' => $keys,
], JSON_THROW_ON_ERROR);
