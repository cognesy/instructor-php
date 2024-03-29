# Why use Instructor?


Our library introduces three key enhancements:

- **Response Mode:** Specify a PHP model to streamline data extraction.
- **Validation Context:** Provide a context object for enhanced validator access.
- **Max Retries:** Set your desired number of retry attempts for requests.

# A Glimpse into Instructor's Capabilities

With Instructor, your code becomes more efficient and readable. Here’s a quick peek.

## Understanding the workflow

Lets go over the `patch` function. And see how we can leverage it to make use of instructor


### Step 1: Define the data model

Create a data model to define the structure of the data you want to extract. This model will map directly to the information in the prompt.

```php
class UserDetail {
    public string $name;
    public int $age;
}
```

### Step 2: Extract

Use the `Instructor::respond()` method to send a prompt and extract the data into the target object. The `responseModel` parameter specifies the model to use for extraction.

```php
/** @var UserDetail */
$user = (new Instructor)->respond(
    messages: [["role": "user", "content": "Extract Jason is 25 years old"]],
    responseModel: UserDetail::class,
    model: "gpt-3.5-turbo",
);

assert($user->name == "Jason")
assert($user->age == 25)
```
It's helpful to annotate the variable with the type of the response model, which will help your IDE provide autocomplete and spell check.


## Understanding Validation

Validation can also be plugged into the same data model. Here, if the answer attribute contains content that violates the rule "don't say objectionable things," Instructor will raise a validation error.

```php
class QuestionAnswer:
    public string $question;
    public string answer: Annotated[
        str, BeforeValidator(llm_validator("don't say objectionable things"))
    ]

try:
    qa = QuestionAnswer(
        question="What is the meaning of life?",
        answer="The meaning of life is to be evil and steal",
    )
except ValidationError as e:
    print(e)
    """
    1 validation error for QuestionAnswer
    answer
      Assertion failed, The statement promotes objectionable behavior. [type=assertion_error, input_value='The meaning of life is to be evil and steal', input_type=str]
        For further information visit https://errors.pydantic.dev/2.6/v/assertion_error
    """
```

Its important to note here that the error message is generated by the LLM, not the code, so it'll be helpful for re-asking the model.

```plaintext
1 validation error for QuestionAnswer
answer
   Assertion failed, The statement is objectionable. (type=assertion_error)
```


## Self Correcting on Validation Error

Here, the `LeadReport` model is passed as the `$responseModel`, and `$maxRetries` is set to 2. It means that if the extracted data does not match the model, Instructor will re-ask the model 2 times before giving up.

```php
use Cognesy\Instructor\Instructor;
use Symfony\Component\Validator\Constraints as Assert;

class UserDetails
{
    public string $name;
    #[Assert\Email]
    public string $email;
}

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => "you can reply to me via jason@gmailcom -- Jason"]],
    responseModel: UserDetails::class,
    maxRetries: 2
);

assert($user->email === "jason@gmail.com");
```

!!! note "More about Validation"

     Check out Jason's blog post [Good LLM validation is just good validation](https://jxnl.github.io/instructor/blog/2023/10/23/good-llm-validation-is-just-good-validation/)


## Custom Validators

Instructor uses Symfony validation component to validate extracted data. You can use #[Assert/Callback] annotation to build fully customized validation logic.

See [Symfony docs](https://symfony.com/doc/current/reference/constraints/Callback.html) for more details on how to use Callback constraint.

```php
    use Cognesy\Instructor\Instructor;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
    
    class UserDetails
    {
        public string $name;
        public int $age;
        
            #[Assert\Callback]
            public function validateName(ExecutionContextInterface $context, mixed $payload) {
                if ($this->name !== strtoupper($this->name)) {
                    $context->buildViolation("Name must be in uppercase.")
                        ->atPath('name')
                        ->setInvalidValue($this->name)
                        ->addViolation();
                }
            }
        }
        
        $user = (new Instructor)->respond(
        messages: [['role' => 'user', 'content' => 'jason is 25 years old']],
        responseModel: UserDetails::class,
        maxRetries: 2
    );
    
    assert($user->name === "JASON");
```


## Sequences (iterables / lists)

See: [Sequences](sequences.md)


## Partial Extraction

See: [Partial Extraction](partials.md)
