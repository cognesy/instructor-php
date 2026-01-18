<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

// Step 1: Define a class that represents the structure and semantics
// of the data you want to extract
enum TaskStatus : string {
    case Pending = 'pending';
    case Completed = 'completed';
}

enum Role : string {
    case PM = 'pm';
    case Dev = 'dev';
}

class Task {
    public string $title;
    public string $description;
    public DateTimeImmutable $dueDate;
    public Role $owner;
    public TaskStatus $status;
}

class Tasks {
    public DateTime $meetingDate;
    /** @var Task[] */
    public array $tasks;
}

// Step 2: Get the text (or chat messages) you want to extract data from
$text = <<<TEXT
Transcription of meeting from 2024-01-15, 16:00
---
PM: Hey, how's progress on the video transcription engine?
Dev: I've got basic functionality working, but accuracy isn't great yet. Might need to switch to a different API.
PM: So the plan is to research alternatives and provide a comparison? Is it possible by Jan 20th?
Dev: Sure, I'll make it available before the meeting.
PM: The one at 12?
Dev: Yes, at 12. By the way, are we still planning to support real-time transcription?
PM: Yes, it's a key feature. Speaking of which, I need to update the product roadmap. I'll have that ready by Jan 18th.
Dev: Got it. I'll keep that in mind while evaluating APIs. Oh, and the UI for the summary view is ready for review.
PM: Great, I'll take a look tomorrow by 10.
TEXT;

print("Input text:\n");
print($text . "\n\n");

// Step 3: Extract structured data using default language model API (OpenAI)
print("Extracting structured data using LLM...\n\n");
$tasks = (new StructuredOutput)
    ->with(
        messages: $text . "\nReturn the extracted tasks as JSON.",
        responseModel: Tasks::class,
        prompt: 'Extract tasks from the transcript. Set owner to the person responsible for the task (pm or dev).',
        //model: 'gpt-4o-mini',
        mode: OutputMode::JsonSchema,
    )
    ->get();

// Step 4: Now you can use the extracted data in your application
print("Extracted data:\n");

dump($tasks);

assert(isset($tasks->meetingDate));
assert($tasks->meetingDate instanceof DateTime);
assert(count($tasks->tasks) > 0);

foreach ($tasks->tasks as $task) {
    assert($task instanceof Task);
    assert(isset($task->title));
    assert(isset($task->description));
    assert(isset($task->dueDate));
    assert($task->dueDate instanceof DateTimeImmutable);
    assert(isset($task->owner));
    assert($task->owner instanceof Role);
    assert(isset($task->status));
    assert($task->status instanceof TaskStatus);
}
?>
