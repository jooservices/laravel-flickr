<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Support;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\RecordedRequest;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Dto\OAuthToken;
use JOOservices\LaravelFlickr\Service\FlickrService;

trait InteractsWithFlickrAdapters
{
    protected function flickrAs(?OAuthToken $token = null): FlickrService
    {
        $token ??= $this->storeToken();

        return app(FlickrService::class)->as($token->userNsid);
    }

    protected function flickrAnonymous(): FlickrService
    {
        $this->storeApp();

        return app(FlickrService::class)->anonymous();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertAdapterCall(
        callable $invoke,
        string $flickrMethod,
        array $payload,
        ?array $expectedParams = null,
    ): ApiResponseData {
        $this->fakeFlickrResponses([$payload]);

        $response = $invoke();

        $this->assertInstanceOf(ApiResponseData::class, $response);
        $this->assertTrue($response->ok);
        $this->assertFlickrMethodCalled($flickrMethod, $expectedParams);

        return $response;
    }

    /**
     * @param  array<string, mixed>|null  $expectedParams
     */
    protected function assertFlickrMethodCalled(string $flickrMethod, ?array $expectedParams = null): void
    {
        $matched = false;

        foreach (ClientBuilder::recorded() as $request) {
            $this->assertInstanceOf(RecordedRequest::class, $request);
            $params = $this->requestParams($request);

            if (($params['method'] ?? null) !== $flickrMethod) {
                continue;
            }

            if ($expectedParams !== null) {
                foreach ($expectedParams as $key => $value) {
                    $this->assertSame(
                        (string) $value,
                        (string) ($params[$key] ?? ''),
                        "Expected {$flickrMethod} param [{$key}]={$value}",
                    );
                }
            }

            $matched = true;
            break;
        }

        $this->assertTrue($matched, "Expected outbound Flickr method [{$flickrMethod}]");
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestParams(RecordedRequest $request): array
    {
        $query = $request->options['query'] ?? [];
        if (is_array($query) && $query !== []) {
            return $query;
        }

        $form = $request->options['form_params'] ?? [];
        if (is_array($form) && $form !== []) {
            return $form;
        }

        $fromUri = [];
        parse_str((string) parse_url($request->uri, PHP_URL_QUERY), $fromUri);

        return $fromUri;
    }

    protected function fakePhotoId(): string
    {
        return (string) fake()->numerify('##########');
    }

    /**
     * @return array{id: string, title: string}
     */
    protected function fakePhotoItem(?string $id = null): array
    {
        return [
            'id' => $id ?? $this->fakePhotoId(),
            'title' => fake()->sentence(3),
        ];
    }
}
