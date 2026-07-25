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
  class Tags
  class FlickrFacade
  class AbstractFlickrAdapter {
    +appName() string
    +nsid() ?string
    #dispatch(...) ?ApiResponseData
  }
  class FlickrJobDispatcher {
    +dispatch(...) ?ApiResponseData
  }
  class FlickrRequestJob {
    +handle(FlickrCallService) ApiResponseData
    +uniqueId() string
  }
  class FlickrCallService {
    +execute(...) ApiResponseData
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
  FlickrRequestJob --> FlickrCallService
  FlickrCallService --> FlickrClientFactory
  FlickrCallService --> AppRepository
  FlickrCallService --> TokenRepository
  FlickrClientFactory --> LimitingFlickrTransport
  LimitingFlickrTransport --> RequestLimiterInterface
  PersistFlickrData --> AbstractFlickrAdapter
  FlickrCallService ..> LogFlickrActivity : events
  FlickrCallService ..> RecordFlickrEvent : events
  FlickrCallService ..> PersistFlickrData : events
```

## Adapter set

Registered in `FlickrAdapterRegistry::MAP`.

| Class | Namespace const | Persists? |
|---|---|---|
| `Photos` | `photos` | No |
| `People` | `people` | Yes (`getPhotos` / `getPublicPhotos`) |
| `Contacts` | `contacts` | Yes (`getList` only) |
| `Photosets` | `photosets` | Yes (`getPhotos`) |
| `Galleries` | `galleries` | Yes (`getPhotos`) |
| `Favorites` | `favorites` | Yes (`getList`) |
| `Test` | `test` | No |
| `Tags` | `tags` | No |

Adapters that persist implement `PersistsResults` and are invoked by `PersistFlickrData` after `FlickrCallCompleted` — without re-validating tokens. Unknown namespaces no-op (never throw).

## OAuth

```text
FlickrOAuthAuthorizeCommand / host
        → OAuthService::authorize
        → PendingAuthorizationStore (encrypted Redis)
        → user authorizes on Flickr
        → OAuthCallbackController | FlickrOAuthCompleteCommand
        → OAuthCompletionService::completePending
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
