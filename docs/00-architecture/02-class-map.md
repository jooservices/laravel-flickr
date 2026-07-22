# Class map

## Core collaboration

```mermaid
classDiagram
  class FlickrService {
    +connection(name) FlickrService
    +as(nsid) FlickrService
    +anonymous() FlickrService
    +call(...) mixed
    +getClient() Flickr
    +rateLimitStatus() RateLimitStatus
  }
  class AbstractFlickrAdapter {
    +appName() string
    +nsid() ?string
    #dispatch(...) ?ApiResponseData
  }
  class FlickrJobDispatcher {
    +dispatch(...) ?ApiResponseData
  }
  class FlickrRequestJob {
    +handle(...) ApiResponseData
    +uniqueId() string
  }
  class FlickrClientFactory {
    +authenticated(...) Flickr
    +anonymous(...) Flickr
  }
  class LimitingFlickrTransport {
    +request(...) RawResponseData
  }
  class AppRepository
  class TokenRepository
  class RequestLimiterInterface
  class PersistFlickrData
  class LogFlickrActivity
  class RecordFlickrEvent

  FlickrService --> AppRepository
  FlickrService --> TokenRepository
  FlickrService --> AbstractFlickrAdapter : __get
  FlickrService --> FlickrClientFactory : getClient
  AbstractFlickrAdapter --> FlickrJobDispatcher
  FlickrJobDispatcher --> FlickrRequestJob
  FlickrRequestJob --> FlickrClientFactory
  FlickrRequestJob --> AppRepository
  FlickrRequestJob --> TokenRepository
  FlickrClientFactory --> LimitingFlickrTransport
  LimitingFlickrTransport --> RequestLimiterInterface
  PersistFlickrData --> AbstractFlickrAdapter
  FlickrRequestJob ..> LogFlickrActivity : events
  FlickrRequestJob ..> RecordFlickrEvent : events
  FlickrRequestJob ..> PersistFlickrData : events
```

## Adapter set

| Class | Namespace const | Persists? |
|---|---|---|
| `Photos` | `photos` | No |
| `People` | `people` | Yes (`getPhotos` / `getPublicPhotos`) |
| `Contacts` | `contacts` | Yes (`getList` only) |
| `Photosets` | `photosets` | Yes (`getPhotos`) |
| `Galleries` | `galleries` | Yes (`getPhotos`) |
| `Favorites` | `favorites` | Yes (`getList`) |
| `Test` | `test` | No |

Adapters that persist implement `PersistsResults` and are invoked by `PersistFlickrData` after `FlickrCallCompleted` — without re-validating tokens.

## OAuth

```text
FlickrOAuthAuthorizeCommand / host
        → OAuthService::authorize
        → PendingAuthorizationStore (encrypted Redis)
        → user authorizes on Flickr
        → OAuthCallbackController | FlickrOAuthCompleteCommand
        → OAuthService::complete
        → TokenRepository::store
        → FlickrOAuthCompleted
```

## Config resolvers

| Interface | Implementation | Source |
|---|---|---|
| `RateLimitConfigResolverInterface` | `LaravelConfigRateLimitResolver` | `flickr.rate_limit_*` |
| `RuntimeSettingsResolverInterface` | `LaravelConfigRuntimeSettingsResolver` | `flickr.*` runtime keys |

Shared casting: `Support\LaravelConfigValue`.
