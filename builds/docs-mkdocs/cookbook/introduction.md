---
title: Instructor Cookbooks
description: Running and viewing cookbooks with Instructor Hub tool
---

## Overview

Welcome to Instructor cookbooks. The goal of this section is to provide a set of tutorials and examples to help you get started.

Instructor comes with a CLI tool that allows you to view and interact with the tutorials and examples and allows you to find the code snippets you may need to get solution to your problem.

<Warning>
    Examples are only available with Instructor project cloned locally.
    We did not want to include them in the Composer package to keep it lightweight.
</Warning>

### Step 1: Clone Instructor project from Github

To get access to the tutorials and examples, you need to clone the Instructor project from Github:

```bash
$ git clone https://github.com/cognesy/instructor-php.git
```

### Step 2: Create `.env` file

Create a `.env` file in the root directory of your copy of Instructor project and set your LLM API key(s).
You can use the `.env-dist` file as a template.

### Step 3: Check the available tutorials

You can check the available tutorials and examples by running the following command in terminal:

```bash
$ ./bin/instructor-hub list
```


## Available CLI Commands

### List Cookbooks

Run `./bin/instructor-hub list` you can see all the available tutorials and examples.

```bash
$ ./bin/instructor-hub list
```

### Reading a Cookbook

To read a tutorial, you can run `./bin/instructor-hub show {id}` to see the full tutorial in the terminal.

```bash
$ ./bin/instructor-hub show {id}
```

Currently, there is no way to page through the tutorial - feel free to contribute :)



### Running a Cookbook

To run a tutorial, you run `./bin/instructor-hub run {id}` in terminal - it will execute the code and show the output. You need to have your OPENAI_API_KEY set in your environment (.env file in root directory of your copy of instructor-php repo).

```bash
$ ./bin/instructor-hub run {id}
```


### Running all Cookbooks

This is mostly for testing if cookbooks are executed properly, but you can run `./bin/instructor-hub all {id}` to run all the tutorials and examples in the terminal, starting from the one you specify.

```bash
$ ./bin/instructor-hub all {id}
```
