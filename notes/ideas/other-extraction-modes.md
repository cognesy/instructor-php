# Other modes of extraction

> Priority: should have

It is related to compatibility with other LLMs, as some of them may not directly support function calling or support it in a different way (see: Claude).


## JSON_MODE vs function calling

Add JSON_MODE to the LLM class, so it can handle both modes.


## MISTRAL_MODE

Review Jason's Python code to understand how to handle function calling for Mistral.


## YAML

For models not supporting function calling YAML might be an easier way to get structured outputs.
