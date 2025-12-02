# Async

## No streaming

```php
$structuredOutput = new StructuredOutput();
$async = $structuredOutput->request(
    messages: "Jason is 35 years old",
    responseModel: Task::class,
    onDone: function (Task $task) {
        // Completed model
        $this->saveTask($task);
    },
    onError: function (Exception $e) {
        // Handle error
    },
)->async();
// continue execution
```

## With streaming / partials

```php
$structuredOutput = new StructuredOutput();
$async = $structuredOutput->->request(
    messages: "Jason is 35 years old",
    responseModel: Task::class,
    onEachUpdate: function (Task $task) {
        // Partially updated model
        $this->updateTask($task);
    },
    onDone: function (Task $task) {
        // Completed model
        $this->saveTask($task);
    },
    onError: function (Exception $e) {
        // Handle error
    },
)->async();
// continue execution
```

