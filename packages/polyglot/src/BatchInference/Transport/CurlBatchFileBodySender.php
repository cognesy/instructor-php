<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Transport;

use Cognesy\Polyglot\BatchInference\Contracts\CanSendBatchFileBody;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use RuntimeException;

final readonly class CurlBatchFileBodySender implements CanSendBatchFileBody
{
    public function __construct(
        private int $connectTimeoutSeconds = 15,
        private int $requestTimeoutSeconds = 300,
        private int $maxResponseBytes = 1048576,
    ) {
    }

    #[\Override]
    public function send(BatchFileBodyRequest $request): BatchFileUploadResponse
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('The curl extension is required for streamed batch JSON submission.');
        }
        $file = fopen($request->path(), 'rb');
        if ($file === false) {
            throw new BatchSubmissionException('Could not open batch request file.', 'submit', BatchMutationCertainty::NotSent);
        }
        $handle = curl_init($request->url());
        if ($handle === false) {
            fclose($file);
            throw new BatchSubmissionException('Could not initialize batch submission.', 'submit', BatchMutationCertainty::NotSent);
        }
        $headers = [];
        foreach ($request->headers() as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }
        $body = '';
        $inputSizeOption = defined('CURLOPT_INFILESIZE_LARGE')
            ? (int) constant('CURLOPT_INFILESIZE_LARGE')
            : CURLOPT_INFILESIZE;
        try {
            curl_setopt_array($handle, [
                CURLOPT_UPLOAD => true,
                CURLOPT_CUSTOMREQUEST => $request->method(),
                CURLOPT_INFILE => $file,
                $inputSizeOption => filesize($request->path()),
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
                throw new BatchSubmissionException('Batch submission acknowledgement is uncertain.', 'submit', BatchMutationCertainty::MayHaveSucceeded);
            }
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($status < 200 || $status >= 300) {
                $certainty = $status >= 500 ? BatchMutationCertainty::MayHaveSucceeded : BatchMutationCertainty::Rejected;
                throw new BatchSubmissionException("Batch submission returned HTTP {$status}.", 'submit', $certainty);
            }
            return new BatchFileUploadResponse($status, $body);
        } finally {
            fclose($file);
            unset($handle);
        }
    }
}
