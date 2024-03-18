# Configuration

Instructor uses `Configuration` and `ConmponentConfig` classes to handle
configuration of all components of the library.


## `Configuration` class

Instructor uses `Configuration` class to handle configuration of all
components of the library. It is used to define components, the way to
instantiate them and the dependencies between components.

`Configuration` class is responsible for instantiation of the components
(and inject them with the configuration data).



## `ComponentConfig` class

`ComponentConfig` class contains a configuration data of a single component.



## Global `autowire()` function

Instructor comes with a global `autowire()` function containing a default
wiring between components used by the library. It is located in
`config\autowire.php` file.
