# Inference

Get the task.

```php
$structuredOutput = new StructuredOutput();
$task = $structuredOutput->with(
    messages: "Jason is 35 years old",
    responseModel: Task::class,
)->get();

$this->updateView($task);
```
or

```php
$structuredOutput = new StructuredOutput();
$task = $structuredOutput->with(
    messages: "Jason is 35 years old",
    responseModel: Task::class,
)->get();

$this->updateView($task);
```

Get partial updates of task.

```php
$structuredOutput = new StructuredOutput();
$stream = $structuredOutput
    ->with(
        messages: "Jason is 35 years old",
        responseModel: Task::class,
    )
    ->withStreaming()
    ->stream();

foreach($stream->partial as $taskUpdate) {
    // Partially updated model
    $this->updateView($taskUpdate);
    // Complete model is null until done
    // $stream->complete == null
}
// Only now $stream->complete is set & validated
if($stream->complete) {
    $task = $stream->complete;
}
```

Get the list of tasks, one by one.

```php
$structuredOutput = new StructuredOutput();
$list = $structuredOutput->with(
    messages: "Jason is 35 years old",
    responseModel: Sequence::of(Task::class),
)->get();

foreach($list as $taskUpdate) {
    // Partially updated model
    $this->updateView($taskUpdate);
}
```

Get the list of tasks, one by one, with partial updates.

```php
$structuredOutput = new StructuredOutput();
$stream = $structuredOutput->with(
    messages: "Jason is 35 years old",
    responseModel: Sequence::of(Task::class),
    partials: true
)->stream();

foreach($stream as $taskUpdate) {
    // Partially updated model
    $this->updateView($taskUpdate);
}
```
