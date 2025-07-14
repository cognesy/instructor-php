---
title: Upgrading Instructor
description: 'Learn how to upgrade your Instructor installation.'
---

Recent changes to the Instructor package may require some manual fixes in your codebase.


## Step 1: Update the package

Run the following command in your CLI:

```bash
composer update cognesy/instructor
```

## Step 2: Config files

Correct your config files to use new namespaces.


## Step 3: Instructor config path

Correct INSTRUCTOR_CONFIG_PATHS in .env file to `config/instructor` (or your custom path).


## Step 4: Codebase

Make sure that your code follows new namespaces.

Suggestion: use IDE search and replace to find and replace old namespaces with new ones.
