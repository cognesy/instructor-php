# Validation


### Problem and ideas

What about validation in such case? we can already have ```validate()``` method in the schema,
Is it enough?


## Solution

Validation can be also customized by implementing CanSelfValidate interface. It allows you to fully control how the data is validated. At the moment it skips built in Symfony Validator logic, so you have to deal with Symfony validation constraints manually.

