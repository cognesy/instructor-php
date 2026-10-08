<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Preparation;

use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use InvalidArgumentException;
use RuntimeException;

final readonly class BatchInputPreparer
{
    public function __construct(
        private BatchRequestNormalizer $normalizer,
        private CanEncodeBatchItem $encoder,
        private int $maxItems = 50000,
        private int $maxBytes = 200000000,
        private int $maxRecordBytes = 10000000,
    ) {
        if ($maxItems < 1 || $maxBytes < 1 || $maxRecordBytes < 1) {
            throw new InvalidArgumentException('Batch input limits must be positive.');
        }
    }

    public function prepare(BatchItems $items): PreparedBatchInput
    {
        $path = tempnam(sys_get_temp_dir(), 'polyglot-batch-');
        if ($path === false) {
            throw new RuntimeException('Could not allocate a batch input file.');
        }
        chmod($path, 0600);
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            unlink($path);
            throw new RuntimeException('Could not open the batch input file.');
        }

        $success = false;
        try {
            $count = 0;
            $bytes = 0;
            $seen = [];
            foreach ($items as $item) {
                if (isset($seen[$item->key()])) {
                    throw new InvalidArgumentException("Duplicate batch item key: {$item->key()}.");
                }
                if (++$count > $this->maxItems) {
                    throw new InvalidArgumentException('Batch input exceeds the item limit.');
                }
                $seen[$item->key()] = true;
                $record = $this->encoder->encode($this->normalizer->normalize($item));
                $line = json_encode($record, JSON_THROW_ON_ERROR)."\n";
                $lineBytes = strlen($line);
                if ($lineBytes > $this->maxRecordBytes || ($bytes += $lineBytes) > $this->maxBytes) {
                    throw new InvalidArgumentException('Batch input exceeds the configured record or payload limit.');
                }
                $this->writeAll($handle, $line);
            }
            if ($count === 0) {
                throw new InvalidArgumentException('Batch input must contain at least one item.');
            }
            if (!fflush($handle)) {
                throw new RuntimeException('Could not flush the batch input file.');
            }
            $success = true;

            return new PreparedBatchInput($path, $count, $bytes);
        } finally {
            fclose($handle);
            if (!$success) {
                unlink($path);
            }
        }
    }

    /** @param resource $handle */
    private function writeAll($handle, string $line): void
    {
        $offset = 0;
        $length = strlen($line);
        while ($offset < $length) {
            $written = fwrite($handle, substr($line, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write the batch input file.');
            }
            $offset += $written;
        }
    }
}
