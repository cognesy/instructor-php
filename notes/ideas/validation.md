# Validation

> Priority: must have


## Returning errors - array vs typed object

Array is simple and straightforward, but it's not type safe and does not provide a way to add custom methods to the error object.

Typed object is less flexible, but actually might be better for DX.

If the switch to typed object error is decided, current CanSelfValidate need changes as it currently returns an array.


## Validation for custom deserializers

> **Observation:** Symfony Validator does not care whether it validates full / big, complex model or individual objects. It's a good thing, as it allows for partial validation - not property by property, but at least object by object (and separately for nested objects).

Idea: we could have multiple validators connected to the model and executed in a sequence.


