<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Preparation;

use RuntimeException;

final readonly class BatchJsonArrayFile
{
    /** @param array<string, scalar> $fields */
    public static function fromJsonl(PreparedBatchInput $source, string $field = 'requests', array $fields = []): PreparedBatchInput
    {
        $input = fopen($source->path(), 'rb');
        $path = tempnam(sys_get_temp_dir(), 'polyglot-batch-json-');
        if ($input === false || $path === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            throw new RuntimeException('Could not allocate a batch JSON request file.');
        }
        chmod($path, 0600);
        $output = fopen($path, 'wb');
        if ($output === false) {
            fclose($input);
            unlink($path);
            throw new RuntimeException('Could not open the batch JSON request file.');
        }

        $success = false;
        try {
            $prefix = $fields === [] ? '{' : substr(json_encode($fields, JSON_THROW_ON_ERROR), 0, -1).',';
            self::writeAll($output, $prefix.json_encode($field, JSON_THROW_ON_ERROR).':[');
            $first = true;
            while (($line = fgets($input)) !== false) {
                if (!$first) {
                    self::writeAll($output, ',');
                }
                self::writeAll($output, rtrim($line, "\r\n"));
                $first = false;
            }
            self::writeAll($output, ']}');
            if (!fflush($output)) {
                throw new RuntimeException('Could not flush the batch JSON request file.');
            }
            $bytes = ftell($output);
            if ($bytes === false) {
                throw new RuntimeException('Could not measure the batch JSON request file.');
            }
            $success = true;
            return new PreparedBatchInput($path, $source->count(), $bytes);
        } finally {
            fclose($input);
            fclose($output);
            if (!$success) {
                unlink($path);
            }
        }
    }

    /** @param resource $handle */
    private static function writeAll($handle, string $data): void
    {
        $offset = 0;
        $length = strlen($data);
        while ($offset < $length) {
            $written = fwrite($handle, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write the batch JSON request file.');
            }
            $offset += $written;
        }
    }
}
