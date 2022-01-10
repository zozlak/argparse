<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace zozlak\argparse;

use zozlak\argparse\ArgumentParser as AP;

/**
 * Tests based on code examples on https://docs.python.org/3/library/argparse.html
 *
 * @author zozlak
 */
class ArgumentParserTest extends \PHPUnit\Framework\TestCase {

    private ArgumentParser $parser;

    public function setUp(): void {
        $this->parser = new AP(exitOnError: false);
    }

    public function testSimple(): void {

        $this->parser->addArgument(['-f', '--foo']);
        $this->parser->addArgument('bar');

        $args = $this->parser->parseArgs(['BAR']);
        $this->assertEquals((object) ['bar' => 'BAR', 'foo' => null], $args);

        $args = $this->parser->parseArgs(['BAR', '--foo', 'FOO']);
        $this->assertEquals((object) ['bar' => 'BAR', 'foo' => 'FOO'], $args);

        try {
            $this->parser->parseArgs(['--foo', 'FOO']);
            $this->assertTrue(false);
        } catch (ArgparseException $e) {
            $this->assertStringContainsString("Argument bar: value required", $e->getMessage());
        }
    }

    public function testFlags1(): void {
        $this->parser->addArgument('--foo', action: AP::ACTION_STORE_CONST, const: 42);
        $args = $this->parser->parseArgs(['--foo']);
        $this->assertEquals((object) ['foo' => 42], $args);
    }

    public function testFlags2(): void {
        $this->parser->addArgument('--foo', action: AP::ACTION_STORE_TRUE);
        $this->parser->addArgument('--bar', action: AP::ACTION_STORE_FALSE);
        $this->parser->addArgument('--baz', action: AP::ACTION_STORE_FALSE);
        $args = $this->parser->parseArgs(['--foo', '--bar']);
        $this->assertEquals((object) ['foo' => true, 'bar' => false, 'baz' => true], $args);
    }

    public function testActionAppend(): void {
        $this->parser->addArgument('--foo', action: AP::ACTION_APPEND);
        $args = $this->parser->parseArgs(['--foo', '1', '--foo', '2']);
        $this->assertEquals((object) ['foo' => ['1', '2']], $args);
    }

    public function testActionAppendConst(): void {
        $this->parser->addArgument('--foo', dest: 'propname', action: AP::ACTION_APPEND_CONST, const: 'a');
        $this->parser->addArgument('--bar', dest: 'propname', action: AP::ACTION_APPEND_CONST, const: 'b');
        $args = $this->parser->parseArgs(['--foo', '--bar']);
        $this->assertEquals((object) ['propname' => ['a', 'b']], $args);
    }

    public function testActionCount(): void {
        $this->parser->addArgument(['--verbose', '-v'], action: AP::ACTION_COUNT, default: 0);
        $args = $this->parser->parseArgs(['-vvv']);
        $this->assertEquals((object) ['verbose' => 3], $args);
    }

    public function testActionExtend(): void {
        $this->parser->addArgument("--foo", action: AP::ACTION_EXTEND, nargs: AP::NARGS_REQ, type: AP::TYPE_STRING);
        $args = $this->parser->parseArgs(["--foo", "f1", "--foo", "f2", "f3", "f4"]);
        $this->assertEquals((object) ['foo' => ['f1', 'f2', 'f3', 'f4']], $args);
    }

    public function testNargsNumber(): void {
        $this->parser->addArgument('--foo', nargs: 2);
        $this->parser->addArgument('bar', nargs: 1);
        $args = $this->parser->parseArgs(['c', '--foo', 'a', 'b']);
        $this->assertEquals((object) ['bar' => ['c'], 'foo' => ['a', 'b']], $args);
    }

    public function testNargsOptional(): void {
        $this->parser->addArgument('--foo', nargs: AP::NARGS_OPT, const: 'c', default: 'd');
        $this->parser->addArgument('bar', nargs: AP::NARGS_OPT, default: 'd');

        $args = $this->parser->parseArgs(['XX', '--foo', 'YY']);
        $this->assertEquals((object) ['bar' => 'XX', 'foo' => 'YY'], $args);

        $args = $this->parser->parseArgs(['XX', '--foo']);
        $this->assertEquals((object) ['bar' => 'XX', 'foo' => 'c'], $args);

        $args = $this->parser->parseArgs([]);
        $this->assertEquals((object) ['bar' => 'd', 'foo' => 'd'], $args);
    }

    public function testNargsStar(): void {
        $this->parser->addArgument('--foo', nargs: AP::NARGS_STAR);
        $this->parser->addArgument('--bar', nargs: AP::NARGS_STAR);
        $this->parser->addArgument('baz', nargs: AP::NARGS_STAR);
        $args = $this->parser->parseArgs(['a', 'b', '--foo', 'x', 'y', '--bar', '1',
            '2']);
        $this->assertEquals((object) ['bar' => ['1', '2'], 'baz' => ['a', 'b'], 'foo' => [
                    'x', 'y']], $args);
    }

    public function testNargsRequired(): void {
        $this->parser->addArgument('foo', nargs: AP::NARGS_REQ);

        $args = $this->parser->parseArgs(['a', 'b']);
        $this->assertEquals((object) ['foo' => ['a', 'b']], $args);

        try {
            $args = $this->parser->parseArgs([]);
            $this->assertTrue(false);
        } catch (ArgparseException $e) {
            $this->assertStringContainsString('Argument foo: value required', $e->getMessage());
        }
    }

    public function testDefault1(): void {
        $this->parser->addArgument('--foo', default: 42);

        $args = $this->parser->parseArgs(['--foo', '2']);
        $this->assertEquals((object) ['foo' => '2'], $args);

        $args = $this->parser->parseArgs([]);
        $this->assertEquals((object) ['foo' => 42], $args);
    }

    public function testDefault2(): void {
        $this->parser->addArgument('--foo', default: 42);
        $args = $this->parser->parseArgs([], (object) ['foo' => 101]);
        $this->assertEquals((object) ['foo' => 101], $args);
    }

    public function testDefault3(): void {
        $this->parser->addArgument('foo', nargs: AP::NARGS_OPT, default: 42);

        $args = $this->parser->parseArgs(['a']);
        $this->assertEquals((object) ['foo' => 'a'], $args);
    }

    public function testDefault4(): void {
        $this->parser->addArgument('foo', nargs: AP::NARGS_STAR, default: 42);
        $args = $this->parser->parseArgs([]);
        $this->assertEquals((object) ['foo' => 42], $args);
    }

    public function testDefaultAndType(): void {
        $this->parser->addArgument('--length', default: '10', type: AP::TYPE_INT);
        $this->parser->addArgument('--width', default: 10.5, type: AP::TYPE_INT);
        $args = $this->parser->parseArgs();
        $this->assertEquals((object) ['length' => 10, 'width' => 10.5], $args);
    }

    public function testSuppress(): void {
        $this->parser->addArgument('--foo', default: AP::DEFAULT_SUPPRESS);
        $args = $this->parser->parseArgs([]);
        $this->assertEquals((object) [], $args);
        $args = $this->parser->parseArgs(['--foo', '1']);
        $this->assertEquals((object) ['foo' => '1'], $args);
    }

    public function testCustomType(): void {
        $this->parser->addArgument('short_title', type: fn($x) => str_replace(' ', '-', $x));
        $args = $this->parser->parseArgs(['The Tale of Two Cities']);
        $this->assertEquals((object) ['short_title' => 'The-Tale-of-Two-Cities'], $args);
    }

    public function testChoices1(): void {
        $this->parser->addArgument('move', choices: ['rock', 'paper', 'scissors']);

        $args = $this->parser->parseArgs(['rock']);
        $this->assertEquals((object) ['move' => 'rock'], $args);

        try {
            $args = $this->parser->parseArgs(['fire']);
            $this->assertTrue(false);
        } catch (ArgparseException $e) {
            $this->assertStringContainsString("Argument move: invalid choice 'fire' (choose from 'rock', 'paper', 'scissors')", $e->getMessage());
        }
    }

    public function testChoices2(): void {
        $this->parser->addArgument('door', type: AP::TYPE_INT, choices: [1, 2, 3]);

        $args = $this->parser->parseArgs(['3']);
        $this->assertEquals((object) ['door' => 3], $args);

        try {
            $args = $this->parser->parseArgs(['4']);
        } catch (ArgparseException $e) {
            $this->assertStringContainsString("Argument door: invalid choice '4' (choose from '1', '2', '3')", $e->getMessage());
        }
    }

    public function testRequired(): void {
        $this->parser->addArgument('--foo', required: true);

        $args = $this->parser->parseArgs(['--foo', 'BAR']);
        $this->assertEquals((object) ['foo' => 'BAR'], $args);

        try {
            $args = $this->parser->parseArgs([]);
        } catch (ArgparseException $e) {
            $this->assertStringContainsString("Argument --foo: value required", $e->getMessage());
        }
    }

    public function testHelpString1(): void {
        $parser   = new ArgumentParser('frobble');
        $parser->addArgument('--foo', action: 'store_true', help: 'foo the bars before frobbling');
        $parser->addArgument('bar', nargs: '+', help: 'one of the bars to be frobbled');
        $expected = <<<S
usage: frobble [-h] [--foo] bar [bar ...]

positional arguments:
  bar                   one of the bars to be frobbled

optional arguments:
  -h, --help            show this help message and exit
  --foo                 foo the bars before frobbling

S;
        $this->assertEquals($expected, (string) $parser);
    }

    public function testHelpString2(): void {
        $parser   = new ArgumentParser('frobble');
        $parser->addArgument('bar', nargs: AP::NARGS_OPT, type: AP::TYPE_INT, default: 42, help: 'the bar (default: %(default)s)');
        $expected = <<<S
usage: frobble [-h] [bar]

positional arguments:
  bar                   the bar (default: 42)

optional arguments:
  -h, --help            show this help message and exit

S;
        $this->assertEquals($expected, (string) $parser);
    }

    public function testHelpString3(): void {
        $parser   = new ArgumentParser('frobble');
        $parser->addArgument('--foo', help: AP::HELP_SUPPRESS);
        $expected = <<<S
usage: frobble [-h]

optional arguments:
  -h, --help            show this help message and exit

S;
        $this->assertEquals($expected, (string) $parser);
    }

    public function testHelpString4(): void {
        $parser   = new ArgumentParser('frobble');
        $parser->addArgument('--foo');
        $parser->addArgument('bar');
        $expected = <<<S
usage: frobble [-h] [--foo FOO] bar

positional arguments:
  bar

optional arguments:
  -h, --help            show this help message and exit
  --foo FOO

S;
        $this->assertEquals($expected, (string) $parser);
    }

    public function testHelpString5(): void {
        $parser   = new ArgumentParser('PROG');
        $parser->addArgument('--foo', metavar: 'YYY');
        $parser->addArgument('bar', metavar: 'XXX');
        $expected = <<<S
usage: PROG [-h] [--foo YYY] XXX

positional arguments:
  XXX

optional arguments:
  -h, --help            show this help message and exit
  --foo YYY

S;
        $this->assertEquals($expected, (string) $parser);
    }

    public function testStrangeNames(): void {
        $this->parser->addArgument(['-f', '--foo-bar', '--foo']);
        $this->parser->addArgument(['-x', '-y']);

        $args = $this->parser->parseArgs(['-f', '1', '-x', '2']);
        $this->assertEquals((object) ['foo_bar' => '1', 'x' => '2'], $args);

        $args = $this->parser->parseArgs(['--foo', '1', '-y', '2']);
        $this->assertEquals((object) ['foo_bar' => '1', 'x' => '2'], $args);
    }

    public function testDest(): void {
        $this->parser->addArgument('--foo', dest: 'bar');
        $args = $this->parser->parseArgs(['--foo', 'XXX']);
        $this->assertEquals((object) ['bar' => 'XXX'], $args);
    }
}
