<?php

declare(strict_types=1);

use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\CurlBatchFileUploader;

require_once dirname(__DIR__, 2).'/Support/BatchInference/wait-for-loopback.php';

it('uploads a real multipart file to a loopback receiver', function () {
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
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $support);
    expect($server)->not->toBeFalse();

    $input = tempnam(sys_get_temp_dir(), 'batch-upload-test-');
    $payload = "{\"custom_id\":\"first\"}\n{\"custom_id\":\"second\"}\n";
    file_put_contents($input, $payload);

    try {
        waitForBatchLoopback($port);

        $response = (new CurlBatchFileUploader())->upload(new BatchFileUpload(
            "http://127.0.0.1:{$port}/upload-server.php",
            $input,
            ['Authorization' => 'Bearer local-test'],
            ['purpose' => 'batch'],
        ));
        $body = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

        expect($response->statusCode())->toBe(200)
            ->and($body['sha256'])->toBe(hash('sha256', $payload))
            ->and($body['bytes'])->toBe(strlen($payload))
            ->and($body['purpose'])->toBe('batch')
            ->and($body['contentType'])->toStartWith('multipart/form-data; boundary=');
    } finally {
        unlink($input);
        proc_terminate($server);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($server);
    }
});
