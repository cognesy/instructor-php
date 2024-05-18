# Better control over deserialization

> Priority: must have

We need custom deserializer or easier way of customizing existing one.
Specific need is #[Description] attribute, which should be used to generate description.

Another reason is that we need to handle custom types, such as Money, Date, etc. Some of them may not be supported by Symfony Serializer out of the box. (COMMENT: this can be achieved by writing custom Symfony deserializers).

Need to document how to write and plug in custom field / object deserializer into Instructor.

Custom deserialization strategy is also needed for partial updates, maybe for streaming too.

### CanProcessResponseMessage

To manually process text of response.

### CanProcessRawResponse

To work with raw response object.

