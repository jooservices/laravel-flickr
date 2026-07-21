# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.x | Yes |

## Reporting a vulnerability

Report security issues privately to **admin@jooservices.com**. Do not open public issues for undisclosed vulnerabilities.

## Secrets

- Never commit Flickr API keys, secrets, or OAuth tokens.
- Host apps must encrypt tokens at rest when persisting them.
- This package does not log credentials.
