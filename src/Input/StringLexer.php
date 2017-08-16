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
 *
 * @internal
 */
final class StringLexer
{
    private const FIELD_NAME = '/(\p{L}[\p{L}\p{N}_-]*)\s*:/Au';

    public const PATTERN_MATCH = 'pattern-match';
    public const SIMPLE_VALUE = 'simple-value';
    public const COMPARE = 'compare';
    public const RANGE = 'range';

    private $data;
    private $cursor;
    private $char;
    private $lineno;
    private $end;

    private $linenoSnapshot;
    private $cursorSnapshot;
    private $charSnapshot;

    public function parse(string $data): void
    {
        $this->data = str_replace(["\r\n", "\r"], "\n", $data);
        $this->end = strlen($this->data);
        $this->lineno = 1;
        $this->cursor = 0;
        $this->char = 0;

        $this->skipEmptyLines();
    }

    public function skipWhitespace(): void
    {
        if (preg_match('/\h+/A', $this->data, $match, 0, $this->cursor)) {
            $this->char += mb_strlen($match[0]);
            $this->cursor += strlen($match[0]);
        }
    }

    public function skipEmptyLines(): void
    {
        if (preg_match('/(?:\s*+)++/A', $this->data, $match, 0, $this->cursor)) {
            $this->moveCursor($match[0]);
        }
    }

    public function moveCursor(string $text): void
    {
        $this->lineno += mb_substr_count($text, "\n");
        $this->cursor += strlen($text);
        $this->char += mb_strlen($text);
    }

    public function snapshot(): void
    {
        $this->linenoSnapshot = $this->lineno;
        $this->cursorSnapshot = $this->cursor;
        $this->charSnapshot = $this->char;
    }

    public function restoreCursor(): void
    {
        if (null === $this->cursorSnapshot) {
            throw new \RuntimeException('Unable to restore cursor because no snapshot was stored.');
        }

        $this->lineno = $this->linenoSnapshot;
        $this->cursor = $this->cursorSnapshot;
        $this->char = $this->charSnapshot;

        $this->linenoSnapshot = null;
        $this->cursorSnapshot = null;
        $this->charSnapshot = null;
    }

    public function isGlimpse(string $data): bool
    {
        return null !== $this->regexOrSingleChar($data);
    }

    public function matchOptional(string $data): ?string
    {
        $match = $this->regexOrSingleChar($data);

        if (null !== $match) {
            $this->moveCursor($match);
            $this->skipWhitespace();

            return $match;
        }

        return null;
    }

    public function expects(string $data, $expected = null): ?string
    {
        $match = $this->regexOrSingleChar($data);

        if (null !== $match) {
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

        return StringLexerException::syntaxError(
            $this->cursor,
            $this->lineno,
            $expected,
            $this->isEnd() ? 'end of string' :
                ("\n" === $this->data[$this->cursor] ? 'line end' :
                    mb_substr($this->data, $this->cursor, min(10, $this->end)))
        );
    }

    public function createFormatException($string): StringLexerException
    {
        return StringLexerException::formatError(
            $this->cursor,
            $this->lineno,
            $string
        );
    }

    public function stringValue(string $allowedNext = ',;)'): string
    {
        $value = '';

        if ($this->isEnd()) {
            throw $this->createSyntaxException('StringValue');
        }

        if ('"' === $this->data[$this->cursor]) {
            $this->moveCursor('"');

            while ("\n" !== $c = mb_substr($this->data, $this->char, 1)) {
                if ('"' === $c) {
                    if ($this->cursor + 1 === $this->end) {
                        break;
                    }
                    if ('"' !== mb_substr($this->data, $this->char + 1, 1)) {
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

            if ("\n" === $c) {
                throw $this->createFormatException(StringLexerException::MISSING_END_QUOTE);
            }

            $this->moveCursor('"');

            $this->skipWhitespace();

            // Detect an user error like: "foo"bar"
            if ($this->cursor < $this->end && !$this->isGlimpse('/['.preg_quote($allowedNext, '/').']/A')) {
                throw $this->createFormatException(StringLexerException::VALUE_QUOTES_MUST_ESCAPE);
            }

            return $value;
        }

        $allowedNextRegex = '/['.preg_quote($allowedNext, '/').']/A';

        while ($this->cursor < $this->end && "\n" !== $c = mb_substr($this->data, $this->char, 1)) {
            if ('"' === $c) {
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

    public function rangeValue(): array
    {
        $lowerInclusive = '[' === ($this->matchOptional('/[[\]]/A') ?? '[');

        $this->skipWhitespace();
        $lowerBound = $this->stringValue('~');

        $this->skipWhitespace();
        $this->expects('~');

        $this->skipWhitespace();
        $upperBound = $this->stringValue('/[[\],;)]/A');

        $upperInclusive = ']' === ($this->matchOptional('/[[\]]/A') ?? ']');

        $this->skipEmptyLines();

        return [$lowerInclusive, $lowerBound, $upperBound, $upperInclusive];
    }

    public function comparisonValue(): array
    {
        $operator = $this->expects('/<>|(?:[<>]=?)/A', 'CompareOperator');

        $this->skipWhitespace();
        $value = $this->stringValue();
        $this->skipEmptyLines();

        return [$operator, $value];
    }

    public function patternMatchValue(): array
    {
        $this->expects('~');

        if ($this->cursor === $this->end) {
            throw $this->createFormatException(StringLexerException::INCOMPLETE_VALUE_PATTERN);
        }

        $negative = false;
        $caseInsensitive = false;

        if (!preg_match('/([^*<>?=]{0,2}\s*)([*<>?=])/A', $this->data, $match, 0, $this->cursor)) {
            throw $this->createSyntaxException('PatternMatch');
        }

        if (preg_match('/\s+/', $match[0])) {
            throw $this->createFormatException(StringLexerException::NO_SPACES_IN_OPERATOR);
        }

        if ('' !== $match[1]) {
            if (!in_array($match[1], ['i!', '!i', 'i', '!'], true)) {
                throw $this->createFormatException(StringLexerException::UNKNOWN_PATTERN_MATCH_FLAG);
            }

            $negative = false !== strpos($match[1], '!');
            $caseInsensitive = false !== strpos($match[1], 'i');
        }

        static $operatorToTypeMapping = [
            '*' => 'CONTAINS',
            '>' => 'STARTS_WITH',
            '<' => 'ENDS_WITH',
            '?' => 'REGEX',
            '=' => 'EQUALS',
        ];

        $type = ($negative ? 'NOT_' : '').$operatorToTypeMapping[$match[2]];

        $this->moveCursor($match[0]);

        $this->skipWhitespace();
        $value = $this->stringValue();
        $this->skipEmptyLines();

        return [$caseInsensitive, $type, $value];
    }

    /**
     * Tries to detect the value-type.
     *
     * The detection is very loosely and stops after
     * the first positive detection. As a result a value
     * may not contain unquoted special characters.
     *
     * @return string
     */
    public function detectValueType(): string
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

        $this->snapshot();

        $this->matchOptional('!');

        if (null !== $this->matchOptional('/[[\]]/A')) {
            $this->restoreCursor();

            return self::RANGE;
        }

        $this->stringValue(',;)~');

        // There is still a chance the value is not a range, but that that's
        // not important now. As a value starting with `~` is invalid already.
        if (null !== $this->matchOptional('~')) {
            $this->restoreCursor();

            return self::RANGE;
        }

        $this->restoreCursor();

        return self::SIMPLE_VALUE;
    }

    public function fieldIdentification(): string
    {
        return mb_substr(trim($this->expects(self::FIELD_NAME, 'FieldIdentification')), 0, -1);
    }

    private function regexOrSingleChar(string $data): ?string
    {
        if ($this->cursor === $this->end) {
            return null;
        }

        if (1 === strlen($data)) {
            return $data === $this->data[$this->cursor] ? $data : null;
        }

        if (preg_match($data, $this->data, $match, 0, $this->cursor)) {
            return $match[0];
        }

        return null;
    }
}
