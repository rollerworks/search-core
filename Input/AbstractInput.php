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

use Rollerworks\Component\Search\ErrorList;
use Rollerworks\Component\Search\InputProcessor;

/**
 * AbstractInput provides the shared logic for the InputProcessors.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
abstract class AbstractInput implements InputProcessor
{
    protected ProcessorConfig $config;
    protected Validator $validator;
    protected ErrorList $errors;

    /** Current nesting level. */
    protected int $level = 0;

    public function __construct(?Validator $validator = null)
    {
        $this->validator = $validator ?? new NullValidator();
    }

    /**
     * This method is called after processing and helps with finding bugs.
     */
    protected function assertLevel0(): void
    {
        if ($this->level > 0) {
            throw new \RuntimeException('Level nesting is not reset to 0, please report this bug.');
        }
    }
}
