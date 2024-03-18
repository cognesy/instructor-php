# Response model contracts

Instructor allows you to customize processing of $responseModel value also by looking at the interfaces the class or instance implements:

- `CanProvideJsonSchema` - implement to be able to provide raw JSON Schema (as an array) of the response model, overriding the default approach of Instructor, which is analyzing $responseModel value class information,
- `CanProvideSchema` - implement to be able to provide `Schema` object of the response model, overriding class analysis stage; can be useful in building object wrappers (see: `Sequence` class),
- `CanDeserializeSelf` - implement to customize the way the response from LLM is deserialized from JSON into PHP object,
- `CanValidateSelf` - implement to customize the way the deserialized object is validated - it fully replaces the default validation process for given response model,
- `CanTransformSelf` - implement to transform the validated object into any target value that will be then passed back to the caller (e.g. unwrap simple type from a class to scalar value)

Methods implemented by those interfaces are executed is following:
- CanProvideJsonSchema - executed during the schema generation phase,
- CanDeserializeSelf - executed during the deserialization phase,
- CanValidateSelf - executed during the validation phase,
- CanTransformSelf - executed during the transformation phase.

When implementing custom response handling strategy, avoid doing all transformations in a single block of code. Split the logic between relevant methods implemented by your class for clarity and easier code maintenance.

For a practical example of using those contracts to customize Instructor processing flow see: `src/Extras/Scalars/`. It contains an implementation of scalar value response support with a wrapper class implementing custom schema provider, deserialization, validation and transformation into requested value type.

#### Example implementation

For a practical example of using those contracts to customize Instructor processing flow see:
- src/Extras/Scalars/
- src/Extras/Sequence/

Examples contain an implementation of custom $responseModel wrappers, e.g. providing scalar value response support with a wrapper class implementing custom schema provider, deserialization, validation and transformation into requested value type.
