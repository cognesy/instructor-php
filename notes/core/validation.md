# Validation

> Priority: must have


## Validation for custom deserializers

> **Observation:** Symfony Validator does not care whether it validates full / big, complex model or individual objects. It's a good thing, as it allows for partial validation - not property by property, but at least object by object (and separately for nested objects).

Idea: we could have multiple validators connected to the model and executed in a sequence.

