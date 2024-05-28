# Signature

 - Signature = inputs + outputs + instructions
 - Inputs = (name, format, description)
 - Outputs = (name, format, description)
 - Instructions = (array of text)

## Instructor integration

How to define signature, so it integrates with Instructor response model

 - Signature as string -> 2 structures = no validation
 - Signature as JSONSchema (w/ .input and .output) -> 2 structures = no validation
 - Signature as 2 structures -> can define structure/field level validation
 - Signature as 2 classes -> can define structure/field level validation
 - Signature as 1 class w/ InputField, OutputField -> can define structure/field level validation

## Signature instance

Has to be arbitrary object, like in instructor. It should use Instructor's code for
building response mode. Ultimately, signature output __is__ the response model.

Signature input should be no different, as it offers validation and serialization to
JSON Schema.

 -> input data, output format (prompt, examples)
 -> messages, schema
 -> LLM response
 -> output data

# Modules

 - Module = signature + setup() + forward()
 - Execution = data -> module -> data
 - How to support data streaming to solve the problem of large data?

## Module types

 - Execute code
 - Structured predictor (w/Instructor)
 - Tools use (w/Instructor)
 - Retrieval from data sources
 - Memory / context storage

# Examples

# Metrics

# Evaluator

# Optimizer

## Compilation

 - Compiled module = prompt + schema descriptions + examples

## How to store compiled module?
