# Iterable results


## Separate endpoint which returns Iterable

Client iterates over it and receives partial updates until iterator is exhausted.
If the model implements iterable, it can be used to return partial updates.

```php
$instructor = new Instructor();
$taskUpdates = $instructor->respond(
    messages: "Notify Jason about the upcoming meeting on Thursday at 10:00 AM",
    responseModel: Task::class,
    stream: true
);
foreach($taskUpdates as $partial) {
    // Partially updated model
    $this->updateView($partial);
}
// do something with task
TaskStore::save($partial);
```



## Separate, optional callback parameter

Client receives partially updated model via callback, while `response()` will still return complete answer when done.

```php
$instructor = new Instructor();
$task = $instructor->respond(
    messages: "Jason is 35 years old",
    responseModel: Task::class,
    onEachUpdate: function (Task $partial) {
        // Partially updated model
        $this->updateView($partial);
    },
    stream: true
);
// do something with task
TaskStore::save($task);
```

