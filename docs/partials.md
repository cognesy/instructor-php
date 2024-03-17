# Partial results

Instructor can process LLM's streamed responses to provide partial updates that you
can use to update the model with new data as the response is being generated.

You can use it to improve user experience by updating the UI with partial data before
the full response is received.

> This feature requires the `stream` option to be set to `true`.

To receive partial results define `onPartialUpdate()` callback that will be called
on every update of the deserializad object.

Instructor is smart about updates, it calculates and compares hashes of the previous
and newly  deserialized version of the model, so you won't get them on every token
received, but only when any property of the object is updated.

 
```php
use Cognesy\Instructor;

function updateUI($person) {
    // Here you get partially completed Person object update UI with the partial result
}

$person = (new Instructor)->request(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
    options: ['stream' => true]
)->onPartialUpdate(
    fn($partial) => updateUI($partial)
)->get();

// Here you get completed and validated Person object
$this->db->save($person); // ...for example: save to DB
```

Partially updated data is not validated while they are received and deserialized.

The object returned from `get()` call is fully validated, so you can safely work
with it, e.g. save it to the database.
