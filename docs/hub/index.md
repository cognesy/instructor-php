# Instructor Hub

Welcome to instructor hub, the goal of this project is to provide a set of tutorials and examples to help you get started, and allow you to pull in the code you need to get started with `instructor`

Make sure you're using the latest version of `instructor` by running:

```bash
composer install cognesy/instructor-php
```

## Contributing

We welcome contributions to the instructor hub, if you have a tutorial or example you'd like to add, please open a pull request in `docs/hub` and we'll review it.

1. The code must be in a single .php file.
2. Please include documentation in the file - check existing examples for the format.
3. Make sure that the code is tested.




## CLI Usage

Instructor hub comes with a command line interface (CLI) that allows you to view and interact with the tutorials and examples and allows you to pull in the code you need to get started with the API.



### List Cookbooks

Run `./hub.sh list` you can see all the available tutorials and examples.

```bash
$ ./hub.sh list
```
...or on Windows:

```cli
$ ./hub.bat list
```

### Reading a Cookbook

To read a tutorial, you can run `./hub.sh show {id}` to see the full tutorial in the terminal.

```bash
$ ./hub.sh show {name or id}
```

Currently, there is no way to page through the tutorial - feel free to contribute :)


### Running a Cookbook

To run a tutorial, you run `./hub.sh run {id}` in terminal - it will execute the code and show the output. You need to have your OPENAI_API_KEY set in your environment (.env file in root directory of your copy of instructor-php repo). 

```bash
$ ./hub.sh run {name or id}
```


### Running all Cookbooks

This is mostly for testing if cookbooks are executed properly, but you can run `./hub.sh all {id}` to run all the tutorials and examples in the terminal.

```bash
$ ./hub.sh all {name or id}
```



## Call for Contributions

We're looking for a bunch more hub examples, if you have a tutorial or example you'd like to add, please open a pull request in `docs/hub` and we'll review it.

- [ ] Converting the cookbooks to the new format
- [ ] Validator examples
- [ ] Data extraction examples
- [ ] Streaming examples (Iterable and Partial)
- [ ] Batch Parsing examples
- [ ] Query Expansion examples
- [ ] Batch Data Processing examples
- [ ] Batch Data Processing examples with Cache

We're also looking for help to catch up with the features available in Instructor for Python (see: https://github.com/jxnl/instructor/blob/main/docs/hub/index.md).

- [ ] Better viewer with pagination
- [ ] Examples database
- [ ] Pulling in the code to your own dir, so you can get started with the API
