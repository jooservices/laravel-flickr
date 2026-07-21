<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Support;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use Jooservices\LaravelFlickr\Dto\PagedResult;

/**
 * Maps common Flickr list envelopes into {@see PagedResult}.
 */
final class PagedResultFactory
{
    /**
     * @param  list<string>  $itemKeys  Candidate keys for the list of rows under the envelope (e.g. contact, photo).
     */
    public static function fromApiResponse(
        ApiResponseData $response,
        string $envelopeKey,
        array $itemKeys = [],
    ): PagedResult {
        if (! $response->ok) {
            return new PagedResult(
                ok: false,
                page: 0,
                pages: 0,
                perPage: 0,
                total: 0,
                items: [],
                errorCode: $response->error?->code,
                errorMessage: $response->error?->message,
                raw: $response,
            );
        }

        $data = $response->data;
        $rawEnvelope = $data[$envelopeKey] ?? null;
        if (! is_array($rawEnvelope)) {
            return new PagedResult(
                ok: true,
                page: 1,
                pages: 1,
                perPage: 0,
                total: 0,
                items: [],
                errorCode: null,
                errorMessage: null,
                raw: $response,
            );
        }

        /** @var array<string, mixed> $envelope */
        $envelope = $rawEnvelope;
        $items = self::extractItems($envelope, $itemKeys);

        return new PagedResult(
            ok: true,
            page: self::intField($envelope, 'page', 1),
            pages: self::intField($envelope, 'pages', 1),
            perPage: self::intField($envelope, 'perpage', self::intField($envelope, 'per_page', count($items))),
            total: self::intField($envelope, 'total', count($items)),
            items: $items,
            errorCode: null,
            errorMessage: null,
            raw: $response,
        );
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @param  list<string>  $itemKeys
     * @return list<array<string, mixed>>
     */
    private static function extractItems(array $envelope, array $itemKeys): array
    {
        foreach ($itemKeys as $key) {
            if (! array_key_exists($key, $envelope)) {
                continue;
            }

            $value = $envelope[$key];
            if ($value === null || $value === '') {
                return [];
            }

            if (! is_array($value)) {
                return [];
            }

            // Single object vs list of objects.
            if (self::isList($value)) {
                /** @var list<mixed> $value */
                return self::onlyAssocRows($value);
            }

            /** @var array<string, mixed> $value */
            return [$value];
        }

        return [];
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private static function onlyAssocRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $items[] = $row;
            }
        }

        return $items;
    }

    /**
     * @param  array<mixed>  $value
     */
    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private static function intField(array $envelope, string $key, int $default): int
    {
        $value = $envelope[$key] ?? null;
        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
