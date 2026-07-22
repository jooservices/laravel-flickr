<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OAuthCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'oauth_token' => ['required', 'string', 'min:8', 'max:255'],
            'oauth_verifier' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    public function oauthToken(): string
    {
        return $this->string('oauth_token')->toString();
    }

    public function oauthVerifier(): string
    {
        return $this->string('oauth_verifier')->toString();
    }
}
