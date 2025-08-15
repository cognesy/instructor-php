## Partial updates

Instructor can process LLM's streamed responses to provide partial updates that you
can use to update the model with new data as the response is being generated.

You can use it to improve user experience by updating the UI with partial data before
the full response is received.

> This feature requires the `stream` option to be set to `true`.

To receive partial results define `onPartialUpdate()` callback that will be called
on every update of the deserializad object.

Instructor is smart about updates, it calculates and compares hashes of the previous
and newly deserialized version of the model, so you won't get them on every token
received, but only when any property of the object is updated.


```php
// @doctest id="93bb"
<?php
use Cognesy\Instructor\StructuredOutput;

function updateUI($person) {
    // Here you get partially completed Person object update UI with the partial result
}

$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->with(
        messages: "His name is Jason, he is 28 years old.",
        options: ['stream' => true]
    )
    ->onPartialUpdate(
        fn($partial) => updateUI($partial)
    )
    ->get();

// Here you get completed and validated Person object
$this->db->save($person); // ...for example: save to DB
```

Partially updated data is not validated while it is received and deserialized.

The object returned from `get()` call is fully validated, so you can safely work
with it, e.g. save it to the database.



## Streaming responses

You can get a stream of responses by calling the `stream()` method instead of `get()`. The `stream()` method is available on both `StructuredOutput` and `PendingStructuredOutput` instances.

```php
// @doctest id="62c3"
// Direct streaming
$stream = $structuredOutput->stream();

// Or via create() method
$pending = $structuredOutput->create();
$stream = $pending->stream();
```

Both approaches return a `StructuredOutputStream` object, which gives you access to the response streamed from LLM and processed by Instructor into structured data.

## StructuredOutputStream Methods

The `StructuredOutputStream` class provides comprehensive methods for processing streaming responses:

### Core Streaming Methods
- `partials()`: Returns an iterable of partial updates from the stream. Only final update is validated, partial updates are only deserialized and transformed.
- `sequence()`: Dedicated to processing `Sequence` response models - returns only completed items in the sequence.
- `responses()`: Generator of partial LLM responses as they are received.

### Result Access Methods
- `finalValue()`: Get the final parsed result (blocks until completion).
- `finalResponse()`: Get the final LLM response (blocks until completion).
- `lastUpdate()`: Returns the last object received and processed by Instructor.
- `lastResponse()`: Returns the last received LLM response.

### Utility Methods
- `usage()`: Get token usage statistics from the streaming response.


### Example: streaming partial responses

```php
// @doctest id="6946"
<?php
use Cognesy\Instructor\StructuredOutput;

$stream = (new StructuredOutput)->with(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
)->stream();

foreach ($stream->partials() as $update) {
    // render updated person view
    // for example:
    $view->updateView($update); // render the updated person view
}

// now you can get final, fully processed person object
$person = $stream->finalValue();
// ...and for example save it to the database
$db->savePerson($person);
```


### Example: streaming sequence items

```php
// @doctest id="6f3e"
<?php
use Cognesy\Instructor\StructuredOutput;

$stream = (new StructuredOutput)
    ->with(
        messages: "Jason is 28 years old, Amanda is 26 and John (CEO) is 40.",
        responseModel: Sequence::of(Participant::class),
    )
    ->stream();

foreach ($stream->sequence() as $update) {
    // append last completed item from the sequence
    // for example:
    $view->appendParticipant($update->last());
}

// now you can get final, fully processed sequence of participants
$participants = $stream->finalValue();
// ...and for example save it to the database
$db->saveParticipants($participants->toArray());
```
