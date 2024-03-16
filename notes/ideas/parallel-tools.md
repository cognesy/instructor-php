# Parallel function calling

> Priority: nice to have

GPT-4-turbo can handle parallel function calling, which allows to return multiple models in a single API call. We do not yet support it, but Python Instructor does.

The benefit is that you can reduce the number of function calls and get extra "intelligence", for example asking LLM to return a series of "operations" it considers relevant to the input.

Need to test it further to understand how it is different from constructing a more complex model that is composed out of other models (or sequences of other models).

One obvious benefit could be that they are returned separately, can be processed separately and, potentially, acted upon in parallel.

It is doable with composite models via custom deserialization, but would be nice not to be forced to do it manually.


