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
 * Caching a processed input provides the most benefit for a really big
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
    private $conditionSerializer;
    private $inputProcessor;
    private $cache;
    private $ttl;

    /**
     * @param \DateInterval|string|int|null $ttl The default Time to life for caches. If no value is sent and
     *                                           the driver supports TTL then the library may set a default value
     *                                           for it or let the driver take care of that. Null means no expiration
     */
    public function __construct(CacheInterface $cache, SearchConditionSerializer $conditionSerializer, InputProcessor $inputProcessor, \DateInterval|string|int|null $ttl = null)
    {
        if (\is_string($ttl)) {
            $ttl = new \DateInterval($ttl);
        }

        $this->conditionSerializer = $conditionSerializer;
        $this->inputProcessor = $inputProcessor;
        $this->cache = $cache;
        $this->ttl = $ttl;
    }

    public function process(ProcessorConfig $config, $input): SearchCondition
    {
        if (\is_string($input)) {
            $cacheKey = $this->getConditionCacheKey($config, $input);

            try {
                return $this->conditionSerializer->unserialize($this->cache->get($cacheKey, []));
            } catch (InvalidArgumentException $e) {
                // No-op
            }

            $result = $this->inputProcessor->process($config, $input);

            if (! $result->isEmpty()) {
                $this->cache->set($cacheKey, $this->conditionSerializer->serialize($result), $config->getCacheTTL() ?? $this->ttl);
            }

            return $result;
        }

        return $this->inputProcessor->process($config, $input);
    }

    private function getConditionCacheKey(ProcessorConfig $config, string $input): string
    {
        return hash('sha256', $config->getFieldSet()->getSetName() . '~' . $input . '~' . \get_class($this->inputProcessor));
    }
}
