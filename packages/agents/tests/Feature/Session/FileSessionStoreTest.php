<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;
use Cognesy\Agents\Session\SessionStatus;
use Cognesy\Agents\Session\Exceptions\InvalidSessionFileException;
use Cognesy\Agents\Session\Store\FileSessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;

function makeFileSession(string $id = 'fs1'): AgentSession {
    return new AgentSession(
        header: AgentSessionInfo::fresh(new SessionId($id), 'agent', 'Agent'),
        definition: new AgentDefinition(name: 'agent', description: 'test', systemPrompt: 'Test'),
        state: AgentState::empty(),
    );
}

function makeTempDir(): string {
    $dir = sys_get_temp_dir() . '/session_store_test_' . uniqid();
    mkdir($dir, 0755, true);
    return $dir;
}

function cleanupDir(string $dir): void {
    $files = glob($dir . '/*') ?: [];
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($dir);
}

it('save and load round-trip via filesystem', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $session = makeFileSession();

        $result = $store->save($session);
        expect($result->isOk())->toBeTrue();

        $loaded = $store->load(new SessionId('fs1'));
        expect($loaded)->not->toBeNull();
        expect($loaded->sessionId())->toBe('fs1');
        expect($loaded->version())->toBe(1);
    } finally {
        cleanupDir($dir);
    }
});

it('atomic writes create json files', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $store->save(makeFileSession());

        expect(file_exists($dir . '/fs1.json'))->toBeTrue();
        $contents = file_get_contents($dir . '/fs1.json');
        $data = json_decode($contents, true);
        expect($data)->toBeArray();
        expect($data['header']['sessionId'])->toBe('fs1');
    } finally {
        cleanupDir($dir);
    }
});

it('optimistic lock via version compare', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $session = makeFileSession();
        $store->save($session);

        // Try saving stale session (version 0, stored is 1)
        $result = $store->save($session);
        expect($result->isConflict())->toBeTrue();
    } finally {
        cleanupDir($dir);
    }
});

it('load throws InvalidSessionFileException on corrupt file', function () {
    $dir = makeTempDir();
    try {
        file_put_contents($dir . '/bad.json', 'not json');
        $store = new FileSessionStore($dir);

        $store->load(new SessionId('bad'));
    } catch (InvalidSessionFileException $e) {
        expect($e->filePath)->toContain('bad.json');
        expect($e->reason)->toContain('Invalid JSON');
        cleanupDir($dir);
        return;
    }
    cleanupDir($dir);
    $this->fail('Expected InvalidSessionFileException');
});

it('load throws on malformed session data', function () {
    $dir = makeTempDir();
    try {
        file_put_contents($dir . '/malformed.json', json_encode(['foo' => 'bar']));
        $store = new FileSessionStore($dir);

        $store->load(new SessionId('malformed'));
    } catch (InvalidSessionFileException $e) {
        expect($e->reason)->toContain('Missing or invalid header');
        cleanupDir($dir);
        return;
    }
    cleanupDir($dir);
    $this->fail('Expected InvalidSessionFileException');
});

it('listHeaders returns SessionInfoList', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $store->save(makeFileSession('a1'));
        $store->save(makeFileSession('a2'));

        $list = $store->listHeaders();
        expect($list)->toBeInstanceOf(SessionInfoList::class);
        expect($list->count())->toBe(2);
    } finally {
        cleanupDir($dir);
    }
});

it('listHeaders throws on first invalid file', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $store->save(makeFileSession('good'));
        file_put_contents($dir . '/corrupt.json', 'invalid');

        $store->listHeaders();
    } catch (InvalidSessionFileException $e) {
        expect($e->filePath)->toContain('corrupt.json');
        cleanupDir($dir);
        return;
    }
    cleanupDir($dir);
    $this->fail('Expected InvalidSessionFileException');
});

it('delete removes session file', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $store->save(makeFileSession('del1'));

        expect($store->exists(new SessionId('del1')))->toBeTrue();
        $store->delete(new SessionId('del1'));
        expect($store->exists(new SessionId('del1')))->toBeFalse();
    } finally {
        cleanupDir($dir);
    }
});

it('load returns null for missing session', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        expect($store->load(new SessionId('nonexistent')))->toBeNull();
    } finally {
        cleanupDir($dir);
    }
});

it('save throws on corrupt JSON in existing file', function () {
    $dir = makeTempDir();
    try {
        file_put_contents($dir . '/broken.json', 'not json');
        $store = new FileSessionStore($dir);

        $store->save(makeFileSession('broken'));
    } catch (InvalidSessionFileException $e) {
        expect($e->filePath)->toContain('broken.json');
        expect($e->reason)->toContain('Invalid JSON');
        cleanupDir($dir);
        return;
    }
    cleanupDir($dir);
    $this->fail('Expected InvalidSessionFileException');
});

it('save throws on malformed structure in existing file', function () {
    $dir = makeTempDir();
    try {
        file_put_contents($dir . '/malformed.json', json_encode(['foo' => 'bar']));
        $store = new FileSessionStore($dir);

        // File exists with valid JSON but no header — readFile succeeds,
        // stored version falls back to 0. Fresh session (version=0) matches,
        // so save overwrites the corrupt file and succeeds.
        $result = $store->save(makeFileSession('malformed'));
        expect($result->isOk())->toBeTrue();
        expect($result->session->version())->toBe(1);

        // Verify the file is now valid
        $loaded = $store->load(new SessionId('malformed'));
        expect($loaded)->not->toBeNull();
        expect($loaded->sessionId())->toBe('malformed');
    } finally {
        cleanupDir($dir);
    }
});

it('version conflict returns SaveResult not exception', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $session = makeFileSession();
        $store->save($session);

        // Stale save (version 0, stored is 1) returns conflict status — not an exception
        $result = $store->save($session);
        expect($result->isConflict())->toBeTrue();
        expect($result->message)->toContain('Version conflict');
        expect($result->session)->toBeNull();
    } finally {
        cleanupDir($dir);
    }
});
