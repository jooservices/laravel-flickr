# Quick start

```php
use Jooservices\LaravelFlickr\Client\FlickrClientFactory;
use Jooservices\LaravelFlickr\Dto\OAuthToken;
use Jooservices\LaravelFlickr\Fetch\ContactsFetcher;

$token = OAuthToken::fromArray([
    'oauth_token' => $storedToken,
    'oauth_token_secret' => $storedSecret,
    'user_nsid' => $nsid,
]);

$client = app(FlickrClientFactory::class)->authenticatedFromConfig($token);
$page = app(ContactsFetcher::class)->listPage($client, 1, 100);

if (! $page->ok) {
    // handle $page->errorCode / $page->errorMessage
    return;
}

foreach ($page->items as $contact) {
    // persist in host repository
}
```

Inject a `FakeFlickrTransport` from the SDK in tests:

```php
use JOOservices\Flickr\Client\FakeFlickrTransport;

$transport = FakeFlickrTransport::new()->pushJson(['stat' => 'ok', 'contacts' => [/* ... */]]);
$client = $factory->authenticated($credentials, $token, $transport);
```
