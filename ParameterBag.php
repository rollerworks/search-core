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

class ParameterBag
{
    /** @var array<string, string> */
    private array $parameters = [];

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(array $parameters = [])
    {
        foreach ($parameters as $name => $value) {
            $this->setParameter($name, $value);
        }
    }

    public function setParameter(string $name, mixed $value): void
    {
        $this->parameters['{' . $name . '}'] = $value;
    }

    public function injectParameters($template): string
    {
        return str_replace(array_keys($this->parameters), array_values($this->parameters), (string) $template);
    }
}
