# Argparse

[![Latest Stable Version](https://poser.pugx.org/zozlak/argparse/v/stable)](https://packagist.org/packages/zozlak/argparse)
![Build status](https://github.com/zozlak/argparse/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/zozlak/argparse/badge.svg?branch=master)](https://coveralls.io/github/zozlak/argparse?branch=master)
[![License](https://poser.pugx.org/zozlak/argparse/license)](https://packagist.org/packages/zozlak/argparse)

A humble PHP clone of Python's [argparse](https://docs.python.org/3/library/argparse.html).

Used to reduce my cognitive workload when switching between Python and PHP.

Implements only primary set of argparse features (see below) but strictly follows Python's argparse behavior.

## API documentation

See the [argparse docs](https://docs.python.org/3/library/argparse.html) with the following remarks:

* [ArgumentParser constructor](https://docs.python.org/3/library/argparse.html#argumentparser-objects) 
  supports only `prog`, `description`, `epilog` and `exit-on-error` parameters.
    * [Argument abbreviations](https://docs.python.org/3/library/argparse.html#prefix-matching) are not implement.
* Almost all features of the [add_argument()](https://docs.python.org/3/library/argparse.html#the-add-argument-method) method are implemented.  
  Known discrepancies include:
    * If you want to define many names for a given argument, pass them as an array to the `name` parameter
      (it's impossible to implement it in PHP in exactly the same way it works in Python as PHP handles positional/named parameters in a slightly different way).
    * Lack of support for `%(prog)s` placeholder in the `help` parameter.
    * Lack of support for `metavar` parameter being an array.
* All features described under [other utilities](https://docs.python.org/3/library/argparse.html#other-utilities) **are not** implemented.

