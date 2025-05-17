
Instructor provides several ways the data model of LLM response.


## Using classes

The default way is to use PHP classes to define the data model. You can also use PHPDoc comments to specify the types of fields of the response.
Additionally, you can use attributes to provide more context to the language model or to provide additional instructions to the model.



## Type Hints

Use PHP type hints to specify the type of extracted data.

> Use nullable types to indicate that given field is optional.

```php
<?php

class Person {
    public string $name;
    public ?int $age;
    public Address $address;
}
```

Instructor will only fill in the fields that are public. Private and protected fields are ignored and their values are not going to be extracted (they will be left empty, with default values set as defined in your class).




## Private vs public object field

Instructor only sets public fields of the object with the data provided by LLM.
Private and protected fields are left unchanged. If you want to access them
directly after extraction, consider providing default values for them.

See `examples/PrivateVsPublicFields/run.php` to check the details on the behavior
of extraction for classes with private and public fields.





## DocBlock type hints

You can also use PHP DocBlock style comments to specify the type of extracted data. This is useful when you want to specify property types for LLM, but can't or don't want to enforce type at the code level.

```php
<?php

class Person {
    /** @var string */
    public $name;
    /** @var int */
    public $age;
    /** @var Address $address person's address */
    public $address;
}
```

See PHPDoc documentation for more details on DocBlock: https://docs.phpdoc.org/3.0/guide/getting-started/what-is-a-docblock.html#what-is-a-docblock




## Using DocBlocks as Additional Instructions for LLM

You can use PHP DocBlocks (/** */) to provide additional instructions for LLM at class or field level, for example to clarify what you expect or how LLM should process your data.

Instructor extracts PHP DocBlocks comments from class and property defined and includes them in specification of response model sent to LLM.

Using PHP DocBlocks instructions is not required, but sometimes you may want to clarify your intentions to improve LLM's inference results.

```php
    /**
     * Represents a skill of a person and context in which it was mentioned. 
     */
    class Skill {
        public string $name;
        /** @var SkillType $type type of the skill, derived from the description and context */
        public SkillType $type;
        /** Directly quoted, full sentence mentioning person's skill */
        public string $context;
    }
```



## Attributes for data model descriptions and instructions

Instructor supports `#[Description]` and `#[Instructions]` attributes to provide more
context to the language model or to provide additional instructions to the model.

`#[Description]` attribute is used to describe a class or property in your data model.
Instructor will use this text to provide more context to the language model.

`#[Instructions]` attribute is used to provide additional instructions to the language
model, such as how to process the data.

You can add multiple attributes to a class or property - Instructor will merge
them into a single block of text.

Instructor will still include any PHPDoc comments provided in the class, but
using attributes might be more convenient and easier to read.

```php
<?php
#[Description("Information about user")]
class User {
    #[Description("User's age")]
    public int $age;
    #[Instructions("Make it ALL CAPS")]
    public string $name;
    #[Description("User's job")]
    #[Instructions("Ignore hobbies, identify profession")]
    public string $job;
}
```


## Typed Collections / Arrays

PHP currently [does not support generics](https://wiki.php.net/rfc/generics) or typehints to specify array element types.

Use PHP DocBlock style comments to specify the type of array elements.

```php
<?php
class Person {
    // ...
}

class Event {
    // ...
    /** @var Person[] list of extracted event participants */
    public array $participants;
    // ...
}
```





## Example of complex data extraction

Instructor can retrieve complex data structures from text. Your response model can contain nested objects, arrays, and enums.

```php
<?php
use Cognesy/Instructor/Instructor;

// define a data structures to extract data into
class Person {
    public string $name;
    public int $age;
    public string $profession;
    /** @var Skill[] */
    public array $skills;
}

class Skill {
    public string $name;
    public SkillType $type;
}

enum SkillType : string {
    case Technical = 'technical';
    case Other = 'other';
}

$text = "Alex is 25 years old software engineer, who knows PHP, Python and can play the guitar.";

$person = (new StructuredOutput)->generate(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Person::class,
); // client is passed explicitly, can specify e.g. different base URL

// data is extracted into an object of given class
assert($person instanceof Person); // true

// you can access object's extracted property values
echo $person->name; // Alex
echo $person->age; // 25
echo $person->profession; // software engineer
echo $person->skills[0]->name; // PHP
echo $person->skills[0]->type; // SkillType::Technical
// ...

var_dump($person);
// Person {
//     name: "Alex",
//     age: 25,
//     profession: "software engineer",
//     skills: [
//         Skill {
//              name: "PHP",
//              type: SkillType::Technical,
//         },
//         Skill {
//              name: "Python",
//              type: SkillType::Technical,
//         },
//         Skill {
//              name: "guitar",
//              type: SkillType::Other
//         },
//     ]
// }
```


## Dynamic data schemas with `Structure` class

In case you work with dynamic data schemas, you can use `Structure` class to define the data model.

See [Structures](/advanced/structures) for more details on how to work with dynamic data schemas.
