<?php

declare(strict_types=1);

$socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($socket === false) {
    throw new RuntimeException('Could not allocate memory proof listener: '.$errorMessage);
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);
$support = __DIR__;
$server = proc_open([
    PHP_BINARY, '-d', 'memory_limit=64M', '-d', 'post_max_size=256M',
    '-d', 'upload_max_filesize=256M', '-S', "127.0.0.1:{$port}", '-t', $support,
], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $serverPipes, $support);
if ($server === false) {
    throw new RuntimeException('Could not start memory proof receiver.');
}

try {
    usleep(150000);
    $worker = proc_open([
        PHP_BINARY, '-d', 'memory_limit=64M', $support.'/memory-worker.php',
        "http://127.0.0.1:{$port}/upload-server.php",
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $workerPipes, $support);
    if ($worker === false) {
        throw new RuntimeException('Could not start memory proof worker.');
    }
    fclose($workerPipes[0]);
    $output = stream_get_contents($workerPipes[1]);
    $error = stream_get_contents($workerPipes[2]);
    fclose($workerPipes[1]);
    fclose($workerPipes[2]);
    if (proc_close($worker) !== 0) {
        throw new RuntimeException('Memory proof worker failed: '.$error);
    }
    $proof = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($proof) || $proof['bytes'] < 200000000 || $proof['count'] !== 42
        || $proof['localHash'] !== $proof['remoteHash'] || $proof['bytes'] !== $proof['remoteBytes']
        || $proof['peakBytes'] >= 67108864) {
        throw new RuntimeException('Large batch payload failed the memory/hash/count proof.');
    }
    $keyWorker = proc_open([
        PHP_BINARY, '-d', 'memory_limit=64M', $support.'/key-index-memory-worker.php',
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $keyPipes, $support);
    if ($keyWorker === false) {
        throw new RuntimeException('Could not start key-index memory proof worker.');
    }
    fclose($keyPipes[0]);
    $keyOutput = stream_get_contents($keyPipes[1]);
    $keyError = stream_get_contents($keyPipes[2]);
    fclose($keyPipes[1]);
    fclose($keyPipes[2]);
    if (proc_close($keyWorker) !== 0) {
        throw new RuntimeException('Key-index memory proof worker failed: '.$keyError);
    }
    $keyProof = json_decode($keyOutput, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($keyProof) || $keyProof['count'] !== 50000 || $keyProof['bytes'] < 1000000
        || $keyProof['peakBytes'] >= 67108864) {
        throw new RuntimeException('Short-record key index exceeded its admitted memory bound.');
    }
    $proof['keyIndex'] = $keyProof;
    echo json_encode($proof, JSON_THROW_ON_ERROR)."\n";
} finally {
    proc_terminate($server);
    foreach ($serverPipes as $pipe) {
        fclose($pipe);
    }
    proc_close($server);
}
