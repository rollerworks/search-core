<?php

declare(strict_types=1);

/*
 * This file is part of the RollerworksSearch package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Rollerworks\Component\Search\Input;

use Rollerworks\Component\Search\Exception\StringLexerException;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class StringLexer
{
    public const FIELD_NAME = '/@?_?(\p{L}[\p{L}\p{N}_-]*)\s*:/Au';

    public const PATTERN_MATCH = 'pattern-match';
    public const SIMPLE_VALUE = 'simple-value';
    public const COMPARE = 'compare';
    public const RANGE = 'range';

    private $valueLexers;
    private $data;
    private $cursor;
    private $char;
    private $lineno;
    private $col;
    private $end;
    private $linenoSnapshot;
    private $colSnapshot;
    private $cursorSnapshot;
    private $charSnapshot;

    /**
     * @internal
     *
     * @param \Closure[] $fieldLexers
     */
    public function parse(string $data, array $fieldLexers = []): void
    {
        $this->data = str_replace(["\r\n", "\r"], "\n", $data);
        $this->valueLexers = $fieldLexers;
        $this->end = mb_strlen($this->data, '8bit');

        $this->lineno = 1;
        $this->col = 0;
        $this->cursor = 0;
        $this->char = 0;

        $this->linenoSnapshot = null;
        $this->colSnapshot = null;
        $this->cursorSnapshot = null;
        $this->charSnapshot = null;

        $this->skipEmptyLines();
    }

    /**
     * Skip any form of whitespace (except for empty lines).
     */
    public function skipWhitespace(): void
    {
        if (preg_match('/\h+/A', $this->data, $match, 0, $this->cursor)) {
            $this->moveCursor($match[0]);
        }
    }

    /**
     * Skip any form of whitespace (including for empty lines).
     */
    public function skipEmptyLines(): void
    {
        if (preg_match('/(?:\s*+)++/A', $this->data, $match, 0, $this->cursor)) {
            $this->moveCursor($match[0]);
        }
    }

    public function moveCursor(string $text): void
    {
        $this->lineno += mb_substr_count($text, "\n");
        $this->char += mb_strlen($text);
        $this->cursor += mb_strlen($text, '8bit');

        if (str_contains($text, "\n")) {
            // Find the last newline, start counting the characters from there as our new position.
            $this->col = mb_strlen(mb_substr($text, mb_strrpos($text, "\n")));
        } else {
            $this->col += mb_strlen($text);
        }
    }

    public function snapshot($force = false): void
    {
        if (! $force && $this->charSnapshot !== null) {
            return;
        }

        $this->linenoSnapshot = $this->lineno;
        $this->colSnapshot = $this->col;
        $this->cursorSnapshot = $this->cursor;
        $this->charSnapshot = $this->char;
    }

    public function restoreCursor(): void
    {
        if ($this->cursorSnapshot === null) {
            throw new \RuntimeException('Unable to restore cursor because no snapshot was stored.');
        }

        $this->lineno = $this->linenoSnapshot;
        $this->col = $this->colSnapshot;
        $this->cursor = $this->cursorSnapshot;
        $this->char = $this->charSnapshot;

        $this->linenoSnapshot = null;
        $this->colSnapshot = null;
        $this->cursorSnapshot = null;
        $this->charSnapshot = null;
    }

    public function isGlimpse(string $data): bool
    {
        return $this->regexOrSingleChar($data) !== null;
    }

    /**
     * Matches that the current position matches the $data attribute,
     * and moves the cursor _only_ when there is a positive match.
     *
     * If there is no match the cursor is left at the current position.
     *
     * @param string $data A single character or a fully specified regex (with delimiters and options)
     *
     * @return string|null The matched result or null when not matched
     */
    public function matchOptional(string $data): ?string
    {
        $match = $this->regexOrSingleChar($data);

        if ($match !== null) {
            $this->moveCursor($match);
            $this->skipWhitespace();

            return $match;
        }

        return null;
    }

    /**
     * Expects that the current position matches $data the attribute,
     * and moves the cursor. Or fails with a syntax exception.
     *
     * Caution: When using a regex be sure to use the `A` modifier.
     * Whitespace *after* the match is automatically ignored.
     *
     * @param string               $data     A single character or a fully specified regex (with delimiters and options)
     * @param string|string[]|null $expected
     *
     * @throws StringLexerException when there no match or there is no further data
     */
    public function expects(string $data, $expected = null): string
    {
        $match = $this->regexOrSingleChar($data);

        if ($match !== null) {
            $this->moveCursor($match);
            $this->skipWhitespace();

            return $match;
        }

        throw $this->createSyntaxException($expected ?? $data);
    }

    public function isEnd(): bool
    {
        return $this->cursor >= $this->end;
    }

    public function createSyntaxException($expected): StringLexerException
    {
        $expected = (array) $expected;

        if ($this->isEnd()) {
            return StringLexerException::syntaxErrorUnexpectedEnd(
                $this->col,
                $this->lineno,
                $expected,
                'end of string'
            );
        }

        if ($this->data[$this->cursor] === "\n") {
            return StringLexerException::syntaxErrorUnexpectedEnd(
                $this->col,
                $this->lineno,
                $expected,
                'line end'
            );
        }

        return StringLexerException::syntaxError(
            $this->col,
            $this->lineno,
            $expected,
            mb_substr($this->data, $this->cursor, min(10, $this->end))
        );
    }

    public function createFormatException($string): StringLexerException
    {
        return StringLexerException::formatError(
            $this->col,
            $this->lineno,
            $string
        );
    }

    /**
     * Expect a StringValue.
     *
     * A StringValue consists of non-special characters in any scripture (language)
     * or a QuotedValue. Trailing whitespace are skipped.
     */
    public function stringValue(string $allowedNext = ',;)'): string
    {
        $value = '';

        if ($this->isEnd()) {
            throw $this->createSyntaxException('StringValue');
        }

        if ($this->data[$this->cursor] === '"') {
            $this->moveCursor('"');

            while (($c = mb_substr($this->data, $this->char, 1)) !== "\n") {
                if ($c === '"') {
                    if ($this->cursor + 1 === $this->end) {
                        break;
                    }

                    if (mb_substr($this->data, $this->char + 1, 1) !== '"') {
                        break;
                    }

                    $this->moveCursor($c);
                }

                $value .= $c = mb_substr($this->data, $this->char, 1);
                $this->moveCursor($c);

                if ($this->cursor === $this->end) {
                    throw $this->createFormatException(StringLexerException::MISSING_END_QUOTE);
                }
            }

            if ($c === "\n") {
                throw $this->createFormatException(StringLexerException::MISSING_END_QUOTE);
            }

            $this->moveCursor('"');

            $this->skipWhitespace();

            // Detect an user error like: "foo"bar"
            if ($this->cursor < $this->end && ! $this->isGlimpse('/[' . preg_quote($allowedNext, '/') . ']/A')) {
                throw $this->createFormatException(StringLexerException::VALUE_QUOTES_MUST_ESCAPE);
            }

            return $value;
        }

        $allowedNextRegex = '/[' . preg_quote($allowedNext, '/') . ']/A';

        while ($this->cursor < $this->end && "\n" !== $c = mb_substr($this->data, $this->char, 1)) {
            if ($c === '"') {
                throw $this->createFormatException(StringLexerException::QUOTED_VALUE_REQUIRE_QUOTING);
            }

            if ($this->isGlimpse($allowedNextRegex)) {
                break;
            }

            if ($this->isGlimpse('/[<>[\](),;~!*?=&*]/A')) {
                throw $this->createFormatException(StringLexerException::SPECIAL_CHARS_REQ_QUOTING);
            }

            $value .= $c;
            $this->moveCursor($c);
        }

        $value = rtrim($value);

        if (preg_match('/\s+/', $value)) {
            throw $this->createFormatException(StringLexerException::SPACES_REQ_QUOTING);
        }

        return $value;
    }

    public function fieldIdentification(): string
    {
        return mb_substr(trim($this->expects(self::FIELD_NAME, 'FieldIdentification')), 0, -1);
    }

    //
    // Internal methods. DO NOT USE FOR CUSTOM LEXERS!
    // Write your own lexical parsers as reusing these methods.
    // May result in an endless recursion.
    //

    /**
     * @internal
     */
    public function valuePart(string $fieldName, string $allowedNext = ',;)'): string
    {
        // matches value syntax (with custom lexer) or string
        if (isset($this->valueLexers[$fieldName])) {
            return $this->valueLexers[$fieldName]($this, $allowedNext);
        }

        return $this->stringValue($allowedNext);
    }

    /**
     * @internal
     */
    public function rangeValue(string $name): array
    {
        $lowerInclusive = ($this->matchOptional('/[[\]]/A') ?? '[') === '[';

        $this->skipWhitespace();
        $lowerBound = $this->valuePart($name, '~');

        $this->skipWhitespace();
        $this->expects('~');

        $this->skipWhitespace();
        $upperBound = $this->valuePart($name, '/[[\],;)]/A');

        $upperInclusive = ($this->matchOptional('/[[\]]/A') ?? ']') === ']';

        $this->skipEmptyLines();

        return [$lowerInclusive, $lowerBound, $upperBound, $upperInclusive];
    }

    /**
     * @internal
     */
    public function comparisonValue(string $name): array
    {
        $operator = $this->expects('/<>|(?:[<>]=?)/A', 'CompareOperator');

        $this->skipWhitespace();
        $value = $this->valuePart($name);
        $this->skipEmptyLines();

        return [$operator, $value];
    }

    /**
     * @internal
     */
    public function patternMatchValue(): array
    {
        $this->expects('~');

        if ($this->cursor === $this->end) {
            throw $this->createFormatException(StringLexerException::INCOMPLETE_VALUE_PATTERN);
        }

        $negative = false;
        $caseInsensitive = false;

        if (! preg_match('/([^*<>=]{0,2}\s*)([*<>=])/A', $this->data, $match, 0, $this->cursor)) {
            throw $this->createSyntaxException('PatternMatch');
        }

        if (preg_match('/\s+/', $match[0])) {
            throw $this->createFormatException(StringLexerException::NO_SPACES_IN_OPERATOR);
        }

        if ($match[1] !== '') {
            if (! \in_array($match[1], ['i!', '!i', 'i', '!'], true)) {
                throw $this->createFormatException(StringLexerException::UNKNOWN_PATTERN_MATCH_FLAG);
            }

            $negative = mb_strpos($match[1], '!') !== false;
            $caseInsensitive = mb_strpos($match[1], 'i') !== false;
        }

        static $operatorToTypeMapping = [
            '*' => 'CONTAINS',
            '>' => 'STARTS_WITH',
            '<' => 'ENDS_WITH',
            '=' => 'EQUALS',
        ];

        $type = ($negative ? 'NOT_' : '') . $operatorToTypeMapping[$match[2]];

        $this->moveCursor($match[0]);

        $this->skipWhitespace();
        $value = $this->stringValue();
        $this->skipEmptyLines();

        return [$caseInsensitive, $type, $value];
    }

    /**
     * @internal
     *
     * The detection is very loosely and stops after
     * the first positive detection. As a result, a value
     * may not match unquoted special characters.
     */
    public function detectValueType(string $name): string
    {
        if ($this->cursor === $this->end) {
            return '';
        }

        if ($this->isGlimpse('~')) {
            return self::PATTERN_MATCH;
        }

        // Note that this will also match `><` which is not valid.
        // But it's better to fail with a parse error in `comparisonValue`
        // then accepting the value as a string.
        if ($this->isGlimpse('/(?:[<>]=?)/A')) {
            return self::COMPARE;
        }

        $this->snapshot(true);

        $this->matchOptional('!');

        if ($this->matchOptional('/[[\]]/A') !== null) {
            $this->restoreCursor();

            return self::RANGE;
        }

        $this->valuePart($name, ',;)~');

        // There is still a chance the value is not a range, but that that's
        // not important now. As a value starting with `~` is invalid already.
        //
        // A custom lexer that uses the range character should be specific in matching.
        // Or consider using a combiner like `(value ~ something)`.
        if ($this->matchOptional('~') !== null) {
            $this->restoreCursor();

            return self::RANGE;
        }

        $this->restoreCursor();

        return self::SIMPLE_VALUE;
    }

    private function regexOrSingleChar(string $data): ?string
    {
        if ($this->cursor === $this->end) {
            return null;
        }

        if (mb_strlen($data, '8bit') === 1) {
            return $data === $this->data[$this->cursor] ? $data : null;
        }

        if (preg_match($data, $this->data, $match, 0, $this->cursor)) {
            return $match[0];
        }

        return null;
    }
}
