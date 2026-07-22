# OAuth 1.0a

## Concepts

| Piece | Role |
|---|---|
| `OAuthService` | Request token + access token exchange via SDK |
| `PendingAuthorizationStore` | Encrypted Redis payload: request secret, app name, correlation id |
| CLI | OOB and web-start flows |
| HTTP callback | Completes web redirect flow (GET) |

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

Flickr redirects to the package callback path (`FLICKR_OAUTH_CALLBACK_PATH`). The controller validates `oauth_token` + `oauth_verifier` via `OAuthCallbackRequest`, consumes pending state, stores the token, and returns a JSON envelope (nsid, username, correlation_id).

## Revoke

```bash
php artisan flickr:oauth:revoke default --nsid=12345678@N00
```

Fires `FlickrOAuthRevoked` after delete.

## Security notes

- Access tokens encrypted in MongoDB
- Pending request-token secrets encrypted in Redis
- Completion events do not include secrets
