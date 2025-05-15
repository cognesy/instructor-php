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
<?php
use Cognesy\Instructor\StructuredOutput;

function updateUI($person) {
    // Here you get partially completed Person object update UI with the partial result
}

$person = (new StructuredOutput)->request(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
    options: ['stream' => true]
)->onPartialUpdate(
    fn($partial) => updateUI($partial)
)->get();

// Here you get completed and validated Person object
$this->db->save($person); // ...for example: save to DB
```

Partially updated data is not validated while it is received and deserialized.

The object returned from `get()` call is fully validated, so you can safely work
with it, e.g. save it to the database.



## Streaming responses

You can get a stream of responses by setting the `stream` option to `true` and calling the `stream()` method
instead of `get()`. It returns `Stream` object, which gives you access to the response streamed from LLM and
processed by Instructor into structured data.

Following methods are available to process the stream:

 - `partials()`: Returns a generator of partial updates from the stream. Only final update is validated, partial updates are only deserialized and transformed.
 - `sequence()`: Dedicated to processing `Sequence` response models - returns only completed items in the sequence.
 - `getLastUpdate()`: Returns the last object received and processed by Instructor.

One more method available on `Stream` is `final()`. It returns only the final response object. It **blocks until the response is fully processed**. It is typically used when you only need final result and prefer to use `onPartialUpdate()` or `onSequenceUpdate()` to process partial updates. It's an equivalent to calling `get()` method (but requires `stream` option set to `true`).


### Example: streaming partial responses

```php
<?php
use Cognesy\Instructor\StructuredOutput;

$stream = (new StructuredOutput)->request(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
)->stream();

foreach ($stream->partials() as $update) {
    // render updated person view
    // for example:
    $view->updateView($update); // render the updated person view
}

// now you can get final, fully processed person object
$person = $stream->getLastUpdate();
// ...and for example save it to the database
$db->savePerson($person);
```


### Example: streaming sequence items

```php
<?php
use Cognesy\Instructor\StructuredOutput;

$stream = (new StructuredOutput)->create(
    messages: "Jason is 28 years old, Amanda is 26 and John (CEO) is 40.",
    responseModel: Sequence::of(Participant::class),
)->stream();

foreach ($stream->sequence() as $update) {
    // append last completed item from the sequence
    // for example:
    $view->appendParticipant($update->last());
}

// now you can get final, fully processed sequence of participants
$participants = $stream->getLastUpdate();
// ...and for example save it to the database
$db->saveParticipants($participants->toArray());
```
