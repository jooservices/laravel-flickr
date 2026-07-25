# OAuth 1.0a

## Concepts

| Piece | Role |
|---|---|
| `OAuthService` | Request token + access token exchange via SDK |
| `PendingAuthorizationStore` | Encrypted Redis payload: request secret, app name, correlation id |
| `OAuthCompletionService` | Shared complete path (consume pending → store token) for CLI + HTTP |
| CLI | OOB and web-start flows |
| HTTP callback | Completes web redirect flow (GET); thin controller → completion service |

## CLI — out-of-band

```bash
php artisan flickr:oauth:authorize default
# Visit the printed URL, authorize, copy the verifier
php artisan flickr:oauth:complete --oauth-token=… --verifier=…
```

## CLI — web callback

```bash
php artisan flickr:oauth:authorize default \
  --callback-url=https://app.example/api/v1/oauth/flickr/callback \
  --correlation-id=host-run-123
```

Flickr redirects to the package callback path (`FLICKR_OAUTH_CALLBACK_PATH`). The controller validates `oauth_token` + `oauth_verifier` via `OAuthCallbackRequest`, then `OAuthCompletionService::completePending` consumes pending state and stores the token. Response is a JSON envelope (nsid, username, correlation_id). Missing pending app yields a structured 404 (not an uncaught 500).

## Revoke

```bash
php artisan flickr:oauth:revoke default --nsid=12345678@N00
```

Fires `FlickrOAuthRevoked` after delete.

## Security notes

- Access tokens encrypted in MongoDB
- Pending request-token secrets encrypted in Redis
- Completion events do not include secrets
