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

/**
 * Description of Argument
 *
 * @author zozlak
 */
class Argument {

    const ARGTYPE_POSITIONAL = 1;
    const ARGTYPE_OPTIONAL   = 2;

    static public function getType(string $arg): int {
        return preg_match('/^([-][^0-9]|[-][-])/', $arg) ? self::ARGTYPE_OPTIONAL : self::ARGTYPE_POSITIONAL;
    }

    /**
     * 
     * @var array<string>
     */
    static private array $flagActions   = [
        ArgumentParser::ACTION_STORE_FALSE,
        ArgumentParser::ACTION_STORE_TRUE,
        ArgumentParser::ACTION_STORE_CONST,
        ArgumentParser::ACTION_APPEND_CONST,
        ArgumentParser::ACTION_COUNT,
    ];

    /**
     * 
     * @var array<string>
     */
    static private array $simplifyNargs = [
        ArgumentParser::NARGS_SINGLE,
        ArgumentParser::NARGS_OPT,
    ];

    /**
     * 
     * @var array<mixed>
     */
    private array $values               = [];
    private int $nargsMin;
    private int $nargsMax;
    private bool $simplify;
    private bool $suppress;
    private int $mentioned            = 0;

    /**
     * 
     * @param string $action
     * @param string|int $nargs
     * @param mixed $const
     * @param mixed $default
     * @param mixed $type
     * @param array<mixed> $choices
     * @param bool $required
     * @param string $help
     * @param string $metavar
     * @param string $dest
     * @param string $names
     * @param bool $positional
     * @throws ArgparseException
     */
    public function __construct(private string $action,
                                private string | int $nargs,
                                private mixed $const, private mixed $default,
                                private mixed $type, private array $choices,
                                private bool $required, private string $help,
                                private string $metavar, private string $dest,
                                private string $names, private bool $positional) {
        $this->nargsMin = (int) match ($nargs) {
                ArgumentParser::NARGS_NONE => 0,
                ArgumentParser::NARGS_SINGLE => (int) $required,
                ArgumentParser::NARGS_OPT => 0,
                ArgumentParser::NARGS_STAR => 0,
                ArgumentParser::NARGS_REQ => 1,
                default => $nargs,
            };
        $this->nargsMax = (int) match ($nargs) {
                ArgumentParser::NARGS_NONE => 0,
                ArgumentParser::NARGS_SINGLE => 1,
                ArgumentParser::NARGS_OPT => 1,
                ArgumentParser::NARGS_STAR => PHP_INT_MAX,
                ArgumentParser::NARGS_REQ => PHP_INT_MAX,
                default => $nargs,
            };
        if ($required) {
            $this->nargsMin = 1;
        }

        $metavarDefault = $positional ? $this->dest : mb_strtoupper($this->dest);
        $this->dest     = str_replace('-', '_', $this->dest);
        if (count($this->choices) > 0) {
            $metavarDefault = "{" . implode(',', $this->choices) . "}";
        }
        $this->metavar = empty($this->metavar) ? $metavarDefault : $this->metavar;

        $this->simplify = in_array($nargs, self::$simplifyNargs, true);
        if ($this->simplify && $this->nargsMax > 1) {
            throw new ArgparseException("Simplify and nargsMax > 1 can't go together");
        }

        if (is_string($this->type)) {
            $this->type = match ($this->type) {
                ArgumentParser::TYPE_INT => fn($x) => intval($x),
                ArgumentParser::TYPE_FLOAT => fn($x) => doubleval($x),
                ArgumentParser::TYPE_BOOL => fn($x) => boolval($x),
                ArgumentParser::TYPE_STRING => fn($x) => strval($x),
                default => throw new ArgparseException("Unknown type $this->type"),
            };
        }
        if ($this->type !== null && is_string($this->default)) {
            $fn            = $this->type;
            $this->default = $fn($this->default);
        }

        $this->suppress = $this->default === ArgumentParser::DEFAULT_SUPPRESS;
    }

    public function __toString(): string {
        return $this->toString(false);
    }

    public function toString(bool $short = false): string {
        if ($this->help === ArgumentParser::HELP_SUPPRESS) {
            return '';
        }
        $help = '';

        if ($short) {
            $help .= ($this->required ? " " : " [");
            if ($this->positional) {
                $help .= $this->metavarToString();
            } else {
                $help .= preg_replace('/,.*$/', '', $this->names) . " ";
            }
        } else {
            $help .= "  " . ($this->positional ? $this->metavar : $this->names) . " ";
        }

        if (!$this->positional && !in_array($this->action, self::$flagActions) && $this->action !== ArgumentParser::ACTION_HELP) {
            $help .= $this->metavarToString();
        }
        if ($short) {
            $help = rtrim($help);
            $help .= $this->required ? "" : "]";
        } else {
            $len  = mb_strlen($help);
            $help .= $len <= 24 ? str_repeat(" ", 24 - $len) : "\n                        ";
            $help .= $this->formatHelp();
        }

        return rtrim($help) . ($short ? "" : "\n");
    }

    private function metavarToString(): string {
        return match ($this->nargs) {
            ArgumentParser::NARGS_OPT => $this->metavar,
            ArgumentParser::NARGS_SINGLE => $this->metavar,
            ArgumentParser::NARGS_REQ => "$this->metavar [$this->metavar ...]",
            ArgumentParser::NARGS_STAR => "[$this->metavar [$this->metavar ...]]",
            default => str_repeat($this->metavar, (int) $this->nargs),
        };
    }

    private function formatHelp(): string {
        $from = ['%(type)s', '%(default)s', '%(choices)s', '%(const)s'];
        $to   = [
            is_string($this->type) ? $this->type : '{callable}',
            is_array($this->default) ? implode(', ', $this->default) : (string) $this->default,
            "{" . implode(',', $this->choices) . "}",
            (string) $this->const,
        ];
        return str_replace($from, $to, $this->help);
    }

    /**
     * 
     * @param array<mixed> $argv
     * @param int $pos
     * @param string|null $argName
     * @return int
     * @throws HelpException
     * @throws ArgparseException
     */
    public function addValues(array $argv, int $pos, ?string $argName = null): int {
        if ($this->action === ArgumentParser::ACTION_HELP) {
            throw new HelpException();
        }

        $argName ??= $this->dest;
        $argType = self::getType((string) $argName);
        $this->mentioned++;

        if (in_array($this->action, self::$flagActions)) {
            return $pos;
        }

        $values = [];
        $pos    += (int) ($argType !== self::ARGTYPE_POSITIONAL);
        while ($pos < count($argv) && count($values) < $this->nargsMax && self::getType($argv[$pos]) === self::ARGTYPE_POSITIONAL) {
            $values[] = $argv[$pos];
            $pos++;
        }
        if (count($values) < $this->nargsMin) {
            throw new ArgparseException("Argument $argName: at least $this->nargsMin argument(s) required");
        }
        if (count($values) > 0) {
            $values         = $this->castType($values, $argName);
            $this->values[] = $this->simplify ? $values[0] : $values;
        }
        return $pos - 1;
    }

    public function setValue(object $data, ?string $argName = null): void {
        $argName ??= $this->dest;
        if (count($this->values) === 0) {
            if ($this->required) {
                throw new ArgparseException("Argument $argName: value required");
            }
            if ($this->suppress) {
                return;
            }
        }
        $dest = $this->dest;
        switch ($this->action) {
            case ArgumentParser::ACTION_STORE:
                $fallback    = $this->mentioned ? $this->const ?? $this->default : $this->default;
                $data->$dest = $this->values[count($this->values) - 1] ?? ($data->$dest ?? $fallback);
                break;
            case ArgumentParser::ACTION_STORE_TRUE:
                $data->$dest = $this->mentioned > 0 ? true : ($this->default ?? false);
                break;
            case ArgumentParser::ACTION_STORE_FALSE:
                $data->$dest = $this->mentioned > 0 ? false : ($this->default ?? true);
                break;
            case ArgumentParser::ACTION_STORE_CONST:
                $data->$dest = $this->mentioned > 0 ? $this->const : $this->default;
                break;
            case ArgumentParser::ACTION_APPEND:
                $data->$dest = array_merge($data->$dest ?? [], count($this->values) > 0 ? $this->values : $this->default);
                break;
            case ArgumentParser::ACTION_APPEND_CONST:
                $data->$dest = array_merge($data->$dest ?? [], $this->mentioned > 0 ? array_fill(0, $this->mentioned, $this->const) : $this->default);
                break;
            case ArgumentParser::ACTION_COUNT:
                $data->$dest = $this->mentioned > 0 ? $this->mentioned : $this->default;
                break;
            case ArgumentParser::ACTION_EXTEND:
                $data->$dest = array_merge($data->$dest ?? [], count($this->values) > 0 ? array_merge(...$this->values) : $this->default);
                break;
            case ArgumentParser::ACTION_HELP:
                throw new SuppressException();
        }
    }

    public function reset(): void {
        $this->values    = [];
        $this->mentioned = 0;
    }

    /**
     * 
     * @param array<mixed> $values
     * @param string $argName
     * @return array<mixed>
     * @throws ArgparseException
     */
    private function castType(array $values, string $argName): array {
        if ($this->type === null && count($this->choices) === 0) {
            return $values;
        }
        if ($this->type !== null) {
            $values = array_map($this->type, $values);
        }
        if (count($this->choices) > 0) {
            foreach ($values as $i) {
                if (!in_array($i, $this->choices, true)) {
                    throw new ArgparseException("Argument $argName: invalid choice '$i' (choose from '" . implode("', '", $this->choices) . "')");
                }
            }
        }
        return $values;
    }
}
