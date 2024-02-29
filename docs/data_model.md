## Specifying Data Model

### Type Hints

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

### DocBlock type hints

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


### Typed Collections / Arrays

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


### Complex data extraction

Instructor can retrieve complex data structures from text. Your response model can contain nested objects, arrays, and enums.

```php
<?php

use Cognesy/Instructor;
use OpenAI;

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

enum SkillType {
    case Technical = 'technical';
    case Other = 'other';
}

$text = "Alex is 25 years old software engineer, who knows PHP, Python and can play the guitar.";

$person = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Person::class,
    client: OpenAI::client($yourApiKey),
); // client is passed explicitly, can specify eg. different base URL

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
