# Validation

## Returning errors - array vs typed object

Array is simple and straightforward, but it's not type safe and does not provide a way to add custom methods to the error object.

Typed object is less flexible, but actually might be better for DX.

If the switch to typed object error is decided, current CanSelfValidate need changes as it currently returns an array.

## Solution

Moved to class based validation results. Code is done and integrated into the library.

