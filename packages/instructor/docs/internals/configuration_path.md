---
title: Configuration Path
description: 'How to set up location of Instructor configuration directory for your project'
---

Instructor comes with a set of configuration files and prompt templates that you can publish to your project directory.

There are 2 ways to set up the location of Instructor's configuration directory:
- Using `Settings` class method `setPath()`
- Using environment variable (recommended)

<Info>
To check how to publish configuration files to your project see [Setup](/setup) section.
</Info>


### Setting Configuration Path via `Settings` Class

You can set Instructor configuration path using the `Settings::setPath()` method:

```php
<?php
use Cognesy\Config\Settings;

Settings::setPath('/path/to/config');
?>
```


### Setting Configuration Path via Environment Variable

You can set the path to Instructor's configuration directory in your `.env` file:

```ini
INSTRUCTOR_CONFIG_PATHS='/path/to/config/,another/path'
```



## Configuration Location Resolution

Instructor uses a configuration directory with a set of `.php` files to store its settings, e.g. LLM provider configurations.

Instructor will look for its configuration location in the following order:
- If `Settings::setPath()` has been called, it will use that custom path list,
- If `INSTRUCTOR_CONFIG_PATHS` (or `INSTRUCTOR_CONFIG_PATH`) environment variable is set, it will use that value,
- Finally, it will default to the directory, which is bundled with Instructor package (under `/config`) and contains default set of configuration files.

