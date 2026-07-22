<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Support;

/**
 * Normalizes Flickr list envelopes into typed item arrays for persistence.
 */
final class FlickrResponseItems
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function fromEnvelope(array $data, string $envelopeKey, string $itemKey): array
    {
        $envelope = $data[$envelopeKey] ?? null;
        if (! is_array($envelope)) {
            return [];
        }

        $items = $envelope[$itemKey] ?? null;
        if (! is_array($items)) {
            return [];
        }

        if ($items !== [] && array_is_list($items) === false) {
            $items = [$items];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $row = [];
            foreach ($item as $key => $value) {
                if (is_string($key)) {
                    $row[$key] = $value;
                }
            }
            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public static function ids(array $items, string $key = 'id'): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = $item[$key] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            } elseif (is_int($id) || is_float($id)) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }
}
