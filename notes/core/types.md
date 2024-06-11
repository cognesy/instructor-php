# Types support

## Handling of Sequenceables and Partials

There must be a better, more generic way to do it.

## Type adapters

To handle types like Carbon. Register adapters for given type and
use them in sequence until first succeeds.

This might be also a cleaner way to handle Sequenceables and Scalars.

## Handling of union types

Currently not supported, needs to be supported to allow better interaction with external code, e.g. Carbon dates.

## Handling of some usual data types

- date/time - via Carbon?
- currency??

## Non-empty constructors

Infer constructor arguments from the provided data. This is not trivial,
as params may be objects - hard to handle, may require another constructor
call to instantiate the object, or callables which I don't know yet how
to handle.

## Handling useful, common data types

Currently, there is no special treatment for common data types, such as:

- Date
- Time
- DateTime
- Period
- Duration
- Money
- Currency

There are no tests around those types of data, nor support for parsing that Pydantic has.
