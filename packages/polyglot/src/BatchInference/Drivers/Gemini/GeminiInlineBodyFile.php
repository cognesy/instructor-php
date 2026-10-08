<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Gemini;

use Cognesy\Polyglot\BatchInference\Preparation\PreparedBatchInput;
use RuntimeException;

final readonly class GeminiInlineBodyFile
{
    public static function fromJsonl(PreparedBatchInput $source, string $displayName): PreparedBatchInput
    {
        $input = fopen($source->path(), 'rb');
        $path = tempnam(sys_get_temp_dir(), 'polyglot-gemini-inline-');
        if ($input === false || $path === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            throw new RuntimeException('Could not allocate Gemini inline batch body.');
        }
        chmod($path, 0600);
        $output = fopen($path, 'wb');
        if ($output === false) {
            fclose($input);
            unlink($path);
            throw new RuntimeException('Could not open Gemini inline batch body.');
        }

        $success = false;
        try {
            $prefix = '{"batch":{"displayName":'.json_encode($displayName, JSON_THROW_ON_ERROR)
                .',"inputConfig":{"requests":{"requests":[';
            self::writeAll($output, $prefix);
            $first = true;
            while (($line = fgets($input)) !== false) {
                self::writeAll($output, ($first ? '' : ',').rtrim($line, "\r\n"));
                $first = false;
            }
            self::writeAll($output, ']}}}}');
            if (!fflush($output)) {
                throw new RuntimeException('Could not flush Gemini inline batch body.');
            }
            $bytes = ftell($output);
            if ($bytes === false) {
                throw new RuntimeException('Could not measure Gemini inline batch body.');
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
        while ($offset < strlen($data)) {
            $written = fwrite($handle, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write Gemini inline batch body.');
            }
            $offset += $written;
        }
    }
}
