<?php declare(strict_types=1);

use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Services\BeadsJsonlFileService;
use Cognesy\Auxiliary\Beads\Presentation\Console\TbdApplicationFactory;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->tmpFile = sys_get_temp_dir() . '/tbd_' . bin2hex(random_bytes(6)) . '.jsonl';
    $this->app = (new TbdApplicationFactory())->create();
});

afterEach(function () {
    if (is_file($this->tmpFile)) {
        @unlink($this->tmpFile);
    }
});

it('initializes file and creates/list issues via CLI', function () {
    $init = new CommandTester($this->app->find('init'));
    $init->execute(['--file' => $this->tmpFile]);

    $create = new CommandTester($this->app->find('create'));
    $create->execute([
        '--file' => $this->tmpFile,
        '--title' => 'Test Issue',
        '--description' => 'Sample',
        '--priority' => '1',
        '--type' => 'task',
    ]);

    $list = new CommandTester($this->app->find('list'));
    $list->execute(['--file' => $this->tmpFile]);
    $output = $list->getDisplay();

    expect($output)->toContain('Test Issue')
        ->and($output)->toContain('open');

    $issues = (new BeadsJsonlFileService())->readFile($this->tmpFile);
    expect(count($issues))->toBe(1)
        ->and($issues[0]->title)->toBe('Test Issue')
        ->and($issues[0]->priority->value)->toBe(1);
});

it('adds and removes dependencies via CLI', function () {
    $init = new CommandTester($this->app->find('init'));
    $init->execute(['--file' => $this->tmpFile]);

    $create = new CommandTester($this->app->find('create'));
    $create->execute(['--file' => $this->tmpFile, '--title' => 'A', '--description' => 'a', '--id' => 'A']);
    $create->execute(['--file' => $this->tmpFile, '--title' => 'B', '--description' => 'b', '--id' => 'B']);

    $depAdd = new CommandTester($this->app->find('dep:add'));
    $depAdd->execute(['id' => 'B', '--file' => $this->tmpFile, '--on' => 'A']);

    $issues = (new BeadsJsonlFileService())->readFile($this->tmpFile);
    $deps = $issues[1]->dependencies;
    expect($deps)->toHaveCount(1)
        ->and($deps[0]->dependsOnId)->toBe('A');

    $depRm = new CommandTester($this->app->find('dep:rm'));
    $depRm->execute(['id' => 'B', '--file' => $this->tmpFile, '--on' => 'A']);

    $issuesAfter = (new BeadsJsonlFileService())->readFile($this->tmpFile);
    expect($issuesAfter[1]->dependencies)->toHaveCount(0);
});
