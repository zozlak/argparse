<?php

/*
 * The MIT License
 *
 * Copyright 2022 zozlak.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace zozlak\argparse;

use SplObjectStorage;

/**
 * Description of ArgumentParser
 *
 * @author zozlak
 */
class ArgumentParser {

    const ACTION_STORE        = 'store';
    const ACTION_STORE_TRUE   = 'store_true';
    const ACTION_STORE_FALSE  = 'store_false';
    const ACTION_STORE_CONST  = 'store_const';
    const ACTION_APPEND       = 'append';
    const ACTION_APPEND_CONST = 'const';
    const ACTION_COUNT        = 'count';
    const ACTION_EXTEND       = 'extend';
    const ACTION_HELP         = 'help';
    const TYPE_STRING         = 'string';
    const TYPE_FLOAT          = 'float';
    const TYPE_INT            = 'int';
    const TYPE_BOOL           = 'bool';
    const NARGS_NONE          = '0';
    const NARGS_SINGLE        = '1';
    const NARGS_OPT           = '?';
    const NARGS_STAR          = '*';
    const NARGS_REQ           = '+';
    const DEFAULT_SUPPRESS    = PHP_FLOAT_MIN;
    const HELP_SUPPRESS       = '__SUPPRESS HELP__';

    /**
     * 
     * @var array<string>
     */
    static private array $nonrequiredNargs = [self::NARGS_OPT, self::NARGS_STAR];
    private string $prog;

    /**
     * 
     * @var array<Argument>
     */
    private array $args                    = [];

    /**
     * 
     * @var array<Argument>
     */
    private array $posArgs                 = [];

    public function __construct(?string $prog = null,
                                private string $description = '',
                                private string $epilog = '',
                                private bool $exitOnError = true) {
        $this->prog   = $prog ?? $_SERVER['argv'][0] ?? 'SCRIPT';
        $this->epilog = !empty($epilog) ? "\n$epilog\n" : '';
        $this->addArgument(['-h', '--help'], action: self::ACTION_HELP, help: "show this help message and exit");
    }

    public function __toString(): string {
        $help = "usage: ";

        if (!empty($this->description)) {
            $help .= $this->description;
        } else {
            $help      .= $this->prog;
            $processed = new SplObjectStorage();
            foreach ($this->args as $i) {
                if (!$processed->contains($i)) {
                    $help .= $i->toString(true);
                    $processed->attach($i);
                }
            }
            foreach ($this->posArgs as $i) {
                $help .= $i->toString(true);
            }
        }
        $help .= "\n$this->epilog";

        $help .= count($this->posArgs) > 0 ? "\npositional arguments:\n" : "";
        foreach ($this->posArgs as $i) {
            $help .= $i;
        }
        $help      .= count($this->args) > 0 ? "\noptional arguments:\n" : "";
        $processed = new SplObjectStorage();
        foreach ($this->args as $i) {
            if (!$processed->contains($i)) {
                $help .= $i;
                $processed->attach($i);
            }
        }
        return $help;
    }

    public function printHelp(): void {
        echo (string) $this;
    }

    /**
     * 
     * @param array<string>|string $name
     * @param string $action
     * @param string|int $nargs
     * @param mixed $const
     * @param mixed $default
     * @param null|string|callable $type
     * @param array<mixed> $choices
     * @param bool $required
     * @param string $help
     * @param string $metavar
     * @param string $dest
     * @return void
     * @throws ArgparseException
     */
    public function addArgument(array | string $name,
                                string $action = self::ACTION_STORE,
                                string | int $nargs = self::NARGS_SINGLE,
                                mixed $const = null, mixed $default = null,
                                null | string | callable $type = null,
                                array $choices = [], bool $required = false,
                                string $help = '', string $metavar = '',
                                string $dest = ''): void {
        $names = is_array($name) ? array_values($name) : [$name];
        if (count($names) === 0) {
            throw new ArgparseException("Argument must have a name");
        }
        $argTypes = array_map(fn($x) => Argument::getType($x), $names);
        if (count(array_unique($argTypes)) > 1) {
            throw new ArgparseException("Argument $names[0]] must be either positional or optional");
        }
        $argType = $argTypes[0];
        if (count($names) > 1 && $argType === Argument::ARGTYPE_POSITIONAL) {
            throw new ArgparseException("Positional argument $names[0]] can have only one name");
        }

        // sanitize
        if (empty($dest) && $argType === Argument::ARGTYPE_POSITIONAL) {
            $dest = $names[0];
        } elseif (empty($dest)) {
            foreach ($names as $name) {
                if (str_starts_with($name, '--')) {
                    $dest = substr($name, 2);
                    break;
                }
            }
            if (empty($dest)) {
                $dest = substr($names[0], 1);
            }
        }
        if ($argType === Argument::ARGTYPE_POSITIONAL && !in_array($nargs, self::$nonrequiredNargs)) {
            $required = true;
        }

        // add mappings
        $arg = new Argument($action, $nargs, $const, $default, $type, $choices, $required, $help, $metavar, $dest, implode(', ', $names), $argType === Argument::ARGTYPE_POSITIONAL);
        foreach ($names as $n => $name) {
            if (empty($name)) {
                throw new ArgparseException("Argument must have a name");
            }
            if ($argTypes[$n] === Argument::ARGTYPE_POSITIONAL) {
                $this->posArgs[] = $arg;
            } else {
                $this->args[$name] = $arg;
            }
        }
    }

    /**
     * 
     * @param array<string>|null $args
     * @param object|null $namespace
     * @return object
     * @throws \zozlak\argparse\ArgparseException
     */
    public function parseArgs(?array $args = null, ?object $namespace = null): object {
        $args      ??= array_slice($_SERVER['argv'] ?? [], 1);
        $namespace ??= new \stdClass();

        foreach ($this->args as $i) {
            $i->reset();
        }
        foreach ($this->posArgs as $i) {
            $i->reset();
        }

        try {
            $this->setArgumentValues($args);

            $processedArgs = new SplObjectStorage();
            foreach ($this->posArgs as $name => $arg) {
                if ($processedArgs->contains($arg)) {
                    continue;
                }
                $processedArgs->attach($arg);
                try {
                    $arg->setValue($namespace);
                } catch (SuppressException) {
                    
                }
            }
            foreach ($this->args as $name => $arg) {
                try {
                    $arg->setValue($namespace, $name);
                } catch (SuppressException) {
                    
                }
            }
            return $namespace;
        } catch (ArgparseException $e) {
            if (!$this->exitOnError) {
                throw $e;
            }
            echo $this;
            if ($e instanceof HelpException) {
                exit(0);
            }
            echo $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * 
     * @param array<string> $args
     * @return void
     * @throws ArgparseException
     */
    private function setArgumentValues(array $args): void {
        $pos = 0;
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (Argument::getType($arg) === Argument::ARGTYPE_OPTIONAL) {
                if (str_starts_with($arg, '--')) {
                    if (!isset($this->args[$arg])) {
                        throw new ArgparseException("Unknown argument $arg");
                    }
                    $i = $this->args[$arg]->addValues($args, $i, $arg);
                } else {
                    for ($j = 1; $j < mb_strlen($arg); $j++) {
                        $flag = "-" . mb_substr($arg, $j, 1);
                        if (!isset($this->args[$flag])) {
                            throw new ArgparseException("Unknown argument $flag");
                        }
                        $i = $this->args[$flag]->addValues($args, $i, $flag);
                    }
                }
            } else {
                if (!isset($this->posArgs[$pos])) {
                    throw new ArgparseException("Unrecognized argument $arg");
                }
                $i = $this->posArgs[$pos]->addValues($args, $i);
                $pos++;
            }
        }
    }
}
