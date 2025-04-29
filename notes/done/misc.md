## Instructor::response()/request() default params

Default values are duplicated across method declarations and Request class.
Clean it up.

## Extraction modes

JSON_MODE vs function calling
 - Add JSON_MODE to the LLM class, so it can handle both modes.

MISTRAL_MODE
 - Review Jason's Python code to understand how to handle function calling for Mistral.

### Solution

New client API code (based on Saloon) supports Json/MdJson pretty well.
