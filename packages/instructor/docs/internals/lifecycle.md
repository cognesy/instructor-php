## Instructor's request lifecycle

As Instructor for PHP processes your request, it goes through several stages:

1. Initialize and self-configure (with possible overrides defined by developer).
2. Analyze classes and properties of the response data model specified by developer.
3. Translate data model into a schema that can be provided to LLM.
4. Execute request to LLM using specified messages (content) and response model metadata.
5. Receive a response from LLM or multiple partial responses (if streaming is enabled).
6. Deserialize response received from LLM into originally requested classes and their properties.
7. In case response contained unserializable data - create feedback message for LLM and request regeneration of the response.
8. Execute validations defined by developer on the deserialized data - if any of them fail, create feedback message for LLM and requests regeneration of the response.
9. Repeat the steps 4-8, unless specified limit of retries has been reached or response passes validation.
