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

use Psr\SimpleCache\CacheInterface;
use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\InputProcessor;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\SearchConditionSerializer;

/**
 * Caches the SearchCondition in a PSR-16 (SimpleCache) storage.
 *
 * Caching a processed input provides the most benefit for a large
 * search condition. Most conditions can be processed with easy, and the
 * overhead of caching is not worth it.
 *
 * The FieldSet name and input (as string) are used to generate the cache-key,
 * therefor any changes in the FieldSet configuration MUST invalidate the cache.
 *
 * The cache is should not be a filesystem or long-term storage!
 */
final class CachingInputProcessor implements InputProcessor
{
    private \DateInterval | int | null $ttl;

    /**
     * @param \DateInterval|string|int|null $ttl The default Time to life for caches. If no value is sent and
     *                                           the driver supports TTL then the library may set a default value
     *                                           for it or let the driver take care of that. Null means no expiration.
     *                                           A string is interpreted as a DateInterval.
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SearchConditionSerializer $conditionSerializer,
        private readonly InputProcessor $inputProcessor,
        \DateInterval | string | int | null $ttl = null,
    ) {
        if (\is_string($ttl)) {
            $ttl = new \DateInterval($ttl);
        }

        $this->ttl = $ttl;
    }

    public function process(ProcessorConfig $config, $input): SearchCondition
    {
        $ttl = $config->getCacheTTL() ?? $this->ttl;

        if ($ttl === null || $ttl === 0 || ! \is_string($input)) {
            return $this->inputProcessor->process($config, $input);
        }

        $cacheKey = $this->getConditionCacheKey($config, $input);

        try {
            return $this->conditionSerializer->unserialize($this->cache->get($cacheKey, []));
        } catch (InvalidArgumentException) {
            // No-op
        }

        $result = $this->inputProcessor->process($config, $input);

        if (! $result->isEmpty()) {
            $this->cache->set($cacheKey, $this->conditionSerializer->serialize($result), $ttl);
        }

        return $result;
    }

    private function getConditionCacheKey(ProcessorConfig $config, string $input): string
    {
        return hash('sha256', $config->getFieldSet()->getSetName() . '~' . $input . '~' . $this->inputProcessor::class);
    }
}
