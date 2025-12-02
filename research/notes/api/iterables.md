# Iterable results


## Separate endpoint which returns Iterable

Client iterates over it and receives partial updates until iterator is exhausted.
If the model implements iterable, it can be used to return partial updates.

```php
$structuredOutput = new StructuredOutput();
$taskUpdates = $structuredOutput->with(
    messages: "Notify Jason about the upcoming meeting on Thursday at 10:00 AM",
    responseModel: Task::class,
    options: ['stream' => true]
)->getIterator();
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
$structuredOutput = new StructuredOutput();
$task = $structuredOutput->with(
    messages: "Jason is 35 years old",
    responseModel: Task::class,
    onEachUpdate: function (Task $partial) {
        // Partially updated model
        $this->updateView($partial);
    },
    stream: true
)->get();
// do something with task
TaskStore::save($task);
```

