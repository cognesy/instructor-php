<?php

declare(strict_types=1);

use Cognesy\Polyglot\BatchInference\Preparation\BatchJsonArrayFile;
use Cognesy\Polyglot\BatchInference\Preparation\PreparedBatchInput;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileBodyRequest;
use Cognesy\Polyglot\BatchInference\Transport\CurlBatchFileBodySender;

require_once dirname(__DIR__, 2).'/Support/BatchInference/wait-for-loopback.php';

it('streams a JSON array request body from a file with the expected POST method', function () {
    if (!extension_loaded('curl')) {
        test()->markTestSkipped('curl extension is not installed');
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    expect($socket)->not->toBeFalse();
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);

    $support = dirname(__DIR__, 2).'/Support/BatchInference';
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $support], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, $support);
    expect($server)->not->toBeFalse();

    $path = tempnam(sys_get_temp_dir(), 'batch-json-test-');
    file_put_contents($path, "{\"custom_id\":\"one\"}\n{\"custom_id\":\"two\"}\n");
    $jsonl = new PreparedBatchInput($path, 2, filesize($path));
    $arrayFile = BatchJsonArrayFile::fromJsonl($jsonl);
    try {
        waitForBatchLoopback($port);
        $body = (string) file_get_contents($arrayFile->path());
        $response = (new CurlBatchFileBodySender())->send(new BatchFileBodyRequest(
            "http://127.0.0.1:{$port}/json-server.php",
            $arrayFile->path(),
            ['Content-Type' => 'application/json', 'X-Api-Key' => 'local-test'],
        ));
        $received = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

        expect(json_decode($body, true, flags: JSON_THROW_ON_ERROR))->toBe([
            'requests' => [['custom_id' => 'one'], ['custom_id' => 'two']],
        ])
            ->and($received['method'])->toBe('POST')
            ->and($received['contentType'])->toBe('application/json')
            ->and($received['sha256'])->toBe(hash('sha256', $body))
            ->and($received['bytes'])->toBe(strlen($body));
    } finally {
        $arrayFile->close();
        $jsonl->close();
        proc_terminate($server);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($server);
    }
});
