# Process / business flows

## 1. Authenticated API call (sync)

```mermaid
sequenceDiagram
  participant Host
  participant FS as FlickrService
  participant Ad as Adapter
  participant JD as FlickrJobDispatcher
  participant Job as FlickrRequestJob
  participant Fac as FlickrClientFactory
  participant LT as LimitingFlickrTransport
  participant API as Flickr API
  participant Ev as Listeners

  Host->>FS: connection?(name)
  Host->>FS: as(nsid)
  FS->>FS: TokenRepository.exists(app, nsid)
  Host->>Ad: method(params)
  Ad->>JD: dispatch(..., applyDefaultPerPage?)
  JD->>Job: middleware + handle (sync)
  Job->>Job: event CallStarting
  Job->>Fac: authenticated(credentials, token)
  Fac->>LT: wrap transport
  LT->>LT: limiter.acquire(apiKey)
  LT->>API: HTTP
  API-->>LT: response
  Job->>Job: event CallCompleted
  Job-->>Host: ApiResponseData
  Job->>Ev: Log / Record / Persist
```

## 2. Queued call

Same as above until `FlickrJobDispatcher`: when `$queued = true`, the job is `dispatch()`ed to the queue named by `flickr.queue_name`. Workers run `handle()` with the same middleware and events. Callers observe outcomes via events, not return values.

## 3. Multi-app resolution

```mermaid
flowchart LR
  A[connection name or default] --> B[AppRepository.find]
  B --> C[AppCredentials]
  D[nsid] --> E[TokenRepository.find app+nsid]
  E --> F[OAuthToken]
  C --> G[FlickrClientFactory]
  F --> G
  G --> H[LimitingFlickrTransport keyed by api_key]
```

## 4. OAuth (CLI OOB or web callback)

```mermaid
sequenceDiagram
  participant Dev
  participant CLI as flickr:oauth:authorize
  participant OAuth as OAuthService
  participant Pending as PendingAuthorizationStore
  participant Flickr
  participant CB as Callback or complete CLI
  participant Tokens as TokenRepository

  Dev->>CLI: connection?, callback-url?, correlation-id?
  CLI->>OAuth: authorize(credentials)
  OAuth->>Flickr: request token
  CLI->>Pending: put encrypted secret + app_name
  Dev->>Flickr: authorize in browser
  alt Web callback
    Flickr->>CB: GET oauth_token + oauth_verifier
    CB->>Pending: consume
    CB->>OAuth: complete
  else OOB
    Dev->>CLI: flickr:oauth:complete verifier
    CLI->>Pending: consume
    CLI->>OAuth: complete
  end
  OAuth->>Tokens: store(app, token)
  OAuth->>OAuth: event FlickrOAuthCompleted
```

## 5. Rate limiting

Two layers, always active when the client is built by this package:

1. **Transport** (`LimitingFlickrTransport`) — `acquire()` before every HTTP call; 429 → `triggerCooldown` + `RateLimitedException`.
2. **Job middleware** (`FlickrRateLimitMiddleware`) — on denial: `release(retryAfter)` if queued, else rethrow.

Limiter: Redis Lua for atomic min-gap + hourly window. Disabled path (`flickr.rate_limit_enabled=false`) grants unlimited permits without recycling workers.

`FlickrRateLimitApproaching` fires **once** when usage crosses `warning_threshold_percent` for a connection key (transition), not on every later request.

## 6. Persistence

On `FlickrCallCompleted`, `PersistFlickrData` builds the matching adapter from the container with `(appName, nsid)` and calls `persist()` when the adapter implements `PersistsResults`. Failed or non-matching methods no-op.
