<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\MockHttp;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

enum TaskStatus : string {
    case Pending = 'pending';
    case Completed = 'completed';
}

enum Role : string {
    case PM = 'pm';
    case Dev = 'dev';
}

final class Task
{
    public string $title;
    public string $description;
    public DateTimeImmutable $dueDate;
    public Role $owner;
    public TaskStatus $status;
}

final class Tasks
{
    public DateTime $meetingDate;
    /** @var Task[] */
    public array $tasks;
}

it('deserializes transcript tasks with enums and dates', function () {
    $json = <<<'JSON'
{"meetingDate":"2024-01-15 16:00:00","tasks":[{"title":"Research API alternatives","description":"Compare API options for the transcription engine.","dueDate":"2024-01-20","owner":"dev","status":"pending"},{"title":"Update product roadmap","description":"Update roadmap to include real-time transcription.","dueDate":"2024-01-18","owner":"pm","status":"pending"},{"title":"Review summary UI","description":"Review the summary view UI.","dueDate":"2024-01-16","owner":"pm","status":"pending"}]}
JSON;

    $mockHttp = MockHttp::get([$json]);
    $tasks = (new StructuredOutput)
        ->withHttpClient($mockHttp)
        ->with(
            messages: 'Transcription text',
            responseModel: Tasks::class,
            mode: OutputMode::JsonSchema,
        )
        ->get();

    expect($tasks)->toBeInstanceOf(Tasks::class);
    expect($tasks->meetingDate)->toBeInstanceOf(DateTime::class);
    expect($tasks->tasks)->toBeArray()->toHaveCount(3);
    expect($tasks->tasks[0]->owner)->toBe(Role::Dev);
    expect($tasks->tasks[0]->status)->toBe(TaskStatus::Pending);
    expect($tasks->tasks[1]->owner)->toBe(Role::PM);
    expect($tasks->tasks[2]->dueDate->format('Y-m-d'))->toBe('2024-01-16');
});
