<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Transport;

use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use RuntimeException;

final readonly class CurlBatchFileUploader implements CanUploadBatchFile
{
    public function __construct(
        private int $connectTimeoutSeconds = 15,
        private int $requestTimeoutSeconds = 300,
        private int $maxResponseBytes = 1048576,
    ) {
        if ($connectTimeoutSeconds < 1 || $requestTimeoutSeconds < 1 || $maxResponseBytes < 1) {
            throw new RuntimeException('Batch upload timeouts and response limit must be positive.');
        }
    }

    #[\Override]
    public function upload(BatchFileUpload $request): BatchFileUploadResponse
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('The curl extension is required for batch file upload; inject another CanUploadBatchFile implementation.');
        }

        $handle = curl_init($request->url());
        if ($handle === false) {
            throw new BatchSubmissionException('Could not initialize batch upload.', 'upload', BatchMutationCertainty::NotSent);
        }

        $body = '';
        $fields = $request->fields();
        $fields[$request->fileField()] = new \CURLFile($request->path(), $request->contentType(), $request->fileName());
        $headers = [];
        foreach ($request->headers() as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $fields,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
                CURLOPT_TIMEOUT => $this->requestTimeoutSeconds,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > $this->maxResponseBytes) {
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($handle);
            if ($ok === false) {
                throw new BatchSubmissionException('Batch file upload acknowledgement is uncertain.', 'upload', BatchMutationCertainty::MayHaveSucceeded);
            }
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($status < 200 || $status >= 300) {
                $certainty = $status >= 500 ? BatchMutationCertainty::MayHaveSucceeded : BatchMutationCertainty::Rejected;
                throw new BatchSubmissionException("Batch file upload returned HTTP {$status}.", 'upload', $certainty);
            }

            return new BatchFileUploadResponse($status, $body);
        } finally {
            unset($handle);
        }
    }
}
