<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait Cacheable
{
    /**
     * Generates a unique cache key.
     *
     * @param string $prefix A prefix for the cache key (e.g., 'availability', 'property_details')
     * @param array $params An array of parameters to make the key unique
     * @return string
     */
    protected function generateCacheKey(string $prefix, array $params): string
    {
        $cacheableParams = $params;
        ksort($cacheableParams); // Ensure consistent order for the key
        return $prefix . '_' . md5(http_build_query($cacheableParams));
    }

    /**
     * Generates cache tags and ensure unique tags
     *
     * @param array $tags Base tags (e.g., ['availability_results'])
     * @param string|null $specificIdentifier An optional specific identifier to add as a tag (e.g., a property ID for 'property:ID')
     * @param string|null $specificTagPrefix Prefix for the specific identifier tag (e.g., 'property')
     * @return array
     */
    protected function getCacheTags(array $baseTags, ?string $specificIdentifier = null, ?string $specificTagPrefix = null): array
    {
        $tags = $baseTags;
        if ($specificIdentifier && $specificTagPrefix) {
            $tags[] = $specificTagPrefix . ':' . $specificIdentifier;
        }
        return array_unique($tags);
    }

    /**
     * Executes a callback and caches its result using tags.
     *
     * @param string $keyPrefix Prefix for the cache key
     * @param array $keyParams Parameters to generate the unique part of the cache key
     * @param array $baseCacheTags Base tags for the cache entry
     * @param string|null $specificTagIdentifier Optional specific ID for an additional tag
     * @param string|null $specificTagPrefix Optional prefix for the specific ID tag
     * @param int $ttlInSeconds Time to live for the cache entry
     * @param callable $callback The function that fetches the data if not cached
     * @return mixed
     */
    protected function rememberWithTags(
        string $keyPrefix,
        array $keyParams,
        array $baseCacheTags,
        ?string $specificTagIdentifier,
        ?string $specificTagPrefix,
        int $ttlInSeconds,
        callable $callback
    ) {
        $cacheKey = $this->generateCacheKey($keyPrefix, $keyParams);
        $cacheTags = $this->getCacheTags($baseCacheTags, $specificTagIdentifier, $specificTagPrefix);

        //Debug cache
        Log::debug(class_basename($this) . ': Attempting to retrieve from cache.', ['key' => $cacheKey, 'tags' => $cacheTags]);

        return Cache::tags($cacheTags)->remember($cacheKey, $ttlInSeconds, function () use ($callback, $cacheKey, $keyParams) {
            Log::info(class_basename($this) . ': Cache miss. Fetching fresh data for key: ' . $cacheKey, ['params' => $keyParams]);
            return $callback();
        });
    }
}
