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

namespace Rollerworks\Component\Search;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A ConditionErrorMessage holds an error message,
 * produced during processing.
 *
 * This can be a failed value transformation,
 * too deep nesting, or values overflow.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class ConditionErrorMessage implements TranslatableInterface, \Stringable
{
    /** @var string[] */
    public array $translatedParameters = [];

    public string $translatedDomain = 'validators';

    /**
     * Any array key in $messageParameters will be used as a placeholder in
     * $messageTemplate.
     *
     * @param string               $path                 Path of the error, this is dependent
     *                                                   on the values structure
     * @param string               $message              The translated error message
     * @param string|null          $messageTemplate      The template for the error message
     * @param array<string, mixed> $messageParameters    The parameters that should be
     *                                                   substituted in the message template
     * @param int|null             $messagePluralization The value for error message pluralization
     * @param mixed                $cause                The cause of the error
     */
    public function __construct(
        public string $path,
        public string $message,
        public ?string $messageTemplate = null,
        public array $messageParameters = [],
        public ?int $messagePluralization = null,
        public mixed $cause = null,
    ) {
        $this->messageTemplate ??= $this->message;
    }

    /**
     * @param array<string, mixed> $messageParameters
     */
    public static function withMessageTemplate(string $path, string $messageTemplate, array $messageParameters = [], ?int $messagePluralization = null, mixed $cause = null): self
    {
        return new self(
            $path,
            strtr($messageTemplate, $messageParameters),
            $messageTemplate,
            $messageParameters,
            $messagePluralization,
            $cause
        );
    }

    public static function rawMessage(string $path, string $message, mixed $cause = null): self
    {
        $obj = new self($path, $message, null, [], null, $cause);
        $obj->messageTemplate = null; // Mark as untranslated

        return $obj;
    }

    /**
     * @param string[] $translatedParameters An array of parameter names that need
     *                                       to be translated prior to there usage
     */
    public function setTranslatedParameters(array $translatedParameters): self
    {
        $this->translatedParameters = $translatedParameters;

        return $this;
    }

    public function setTranslatedDomain(string $domain): self
    {
        $this->translatedDomain = $domain;

        return $this;
    }

    public function __toString(): string
    {
        return $this->message;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $parameters = $this->messageParameters;

        // Note that when the input is less than 3 (0 index) characters we don't translate.
        $nestedTranslator = static fn (string $v): string => isset($v[2]) ? $translator->trans($v, [], 'RollerworksSearch', $locale) : $v;

        foreach ($this->translatedParameters as $name) {
            $value = $parameters[$name];
            $parameters[$name] = \is_array($value) ? array_map($nestedTranslator, $value) : $nestedTranslator($value);
        }

        // Because of the formatting we need to pre-translate the parameters.
        // Otherwise we could translate the parameters using a TranslatableInterface value.
        $parameters = $this->formatParameters($parameters);

        return $translator->trans($this->messageTemplate, $parameters, $this->translatedDomain, $locale);
    }

    /**
     * @param array<string, string|int|float|(string|int|float)[]> $messageParameters
     *
     * @return array<string, string>
     */
    private function formatParameters(array $messageParameters): array
    {
        $newParams = [];

        foreach ($messageParameters as $name => $value) {
            if (\is_array($value)) {
                $value = implode(', ', array_map([$this, 'formatValue'], $value));
            } else {
                $value = $this->formatValue($value);
            }

            $newParams[$name] = $value;
        }

        return $newParams;
    }

    private function formatValue(string | int | float $value): string
    {
        $value = (string) $value;

        if (! preg_match('/[\d]/u', $value)) {
            $value = '"' . $value . '"';
        }

        return $value;
    }
}
