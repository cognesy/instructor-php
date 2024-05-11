# Inference

Get the task.

```php
$instructor = new Instructor();
$task = $instructor->respond(
    messages: "Jason is 35 years old",
    responseModel: Task::class,
);

$this->updateView($task);
```
or

```php
$instructor = new Instructor();
$task = $instructor->request(
    messages: "Jason is 35 years old",
    responseModel: Task::class,
)->get();

$this->updateView($task);
```

Get partial updates of task.

```php
$instructor = new Instructor();
$stream = $instructor->request(
    messages: "Jason is 35 years old",
    responseModel: Task::class,
)->stream();

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
$instructor = new Instructor();
$stream = $instructor->request(
    messages: "Jason is 35 years old",
    responseModel: Sequence::of(Task::class),
)->get();

foreach($stream as $taskUpdate) {
    // Partially updated model
    $this->updateView($taskUpdate);
}
```

Get the list of tasks, one by one, with partial updates.

```php
$instructor = new Instructor();
$stream = $instructor->request(
    messages: "Jason is 35 years old",
    responseModel: Sequence::of(Task::class),
    partials: true
)->stream();

foreach($stream as $taskUpdate) {
    // Partially updated model
    $this->updateView($taskUpdate);
}
```
