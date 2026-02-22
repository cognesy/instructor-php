<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\Exceptions\InvalidSessionFileException;
use Cognesy\Agents\Session\Exceptions\SessionConflictException;
use Cognesy\Agents\Session\Exceptions\SessionNotFoundException;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;
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

it('create and load round-trip via filesystem', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $created = $store->create(makeFileSession());

        expect($created->version())->toBe(1);

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
        $store->create(makeFileSession());

        expect(file_exists($dir . '/fs1.json'))->toBeTrue();
        $contents = file_get_contents($dir . '/fs1.json');
        $data = json_decode($contents, true);
        expect($data)->toBeArray();
        expect($data['header']['sessionId'])->toBe('fs1');
    } finally {
        cleanupDir($dir);
    }
});

it('save enforces optimistic version lock', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $session = makeFileSession();
        $created = $store->create($session);
        expect($created->version())->toBe(1);

        $store->save($session);
    } finally {
        cleanupDir($dir);
    }
})->throws(SessionConflictException::class);

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
        $store->create(makeFileSession('a1'));
        $store->create(makeFileSession('a2'));

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
        $store->create(makeFileSession('good'));
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
        $store->create(makeFileSession('del1'));

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

it('save overwrites malformed structure when version matches', function () {
    $dir = makeTempDir();
    try {
        file_put_contents($dir . '/malformed.json', json_encode(['foo' => 'bar']));
        $store = new FileSessionStore($dir);

        $saved = $store->save(makeFileSession('malformed'));
        expect($saved->version())->toBe(1);

        $loaded = $store->load(new SessionId('malformed'));
        expect($loaded)->not->toBeNull();
        expect($loaded->sessionId())->toBe('malformed');
    } finally {
        cleanupDir($dir);
    }
});

it('create throws conflict when session already exists', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $store->create(makeFileSession('fs1'));

        $store->create(makeFileSession('fs1'));
    } finally {
        cleanupDir($dir);
    }
})->throws(SessionConflictException::class);

it('save throws not found for missing session', function () {
    $dir = makeTempDir();
    try {
        $store = new FileSessionStore($dir);
        $store->save(makeFileSession('missing'));
    } finally {
        cleanupDir($dir);
    }
})->throws(SessionNotFoundException::class);
