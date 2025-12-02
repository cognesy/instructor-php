# CLI

> Priority: nice to have

## Simple example

```cli
instruct --messages "Jason is 35 years old" --respond-with UserDetails --response-format yaml
```
It will search for UserFormat.php (PHP class) or UserFormat.json (JSONSchema) in current dir.
We should be able to provide a path to class code / schema definitions directory.
Default response format is JSON, we can render it to YAML (or other supported formats).


## Scalar example

```cli
instruct --messages "Jason is 35 years old" --respond-with Scalar::bool('isAdult')
```
