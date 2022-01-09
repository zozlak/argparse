# Argparse

A humble PHP clone of Python's [argparse](https://docs.python.org/3/library/argparse.html).

Used to reduce my cognitive workload when switching between Python and PHP.

Implements only primary set of argparse features (see below)

## API documentation

See the [argparse docs](https://docs.python.org/3/library/argparse.html) with the following remarks:

* [ArgumentParser constructor](https://docs.python.org/3/library/argparse.html#argumentparser-objects) 
  supports only `prog`, `description`, `epilog` and `exit-on-error` parameters.
    * [Argument abbreviations](https://docs.python.org/3/library/argparse.html#prefix-matching) are not implement.
* All features of the [add_argument()](https://docs.python.org/3/library/argparse.html#the-add-argument-method) method are implemented.
* All features described under [other utilities](https://docs.python.org/3/library/argparse.html#other-utilities) are **not** implemented.

