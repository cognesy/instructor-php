<?php
namespace Tests\Feature\Extras;

use Cognesy\Instructor\Utils\Profiler;
use Tests\Examples\Task\TestTask;

it('can process a simple task', function() {
    Profiler::mark('start');
    $task = new TestTask;
    $result = $task->with(['numberA' => 1, 'numberB' => 2])->get();
    Profiler::mark('end');

    expect($result)->toBe(3);
    expect($task->input('numberA'))->toBe(1);
    expect($task->input('numberB'))->toBe(2);
    expect($task->output('result'))->toBe(3);

    // calculate time taken
    Profiler::dump();
});
