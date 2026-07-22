<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JsonResource */
final class OAuthCallbackResource extends JsonResource
{
    public function __construct(
        private readonly ?string $nsid,
        private readonly ?string $username,
        private readonly ?string $correlationId,
    ) {
        parent::__construct(null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($request);

        return [
            'nsid' => $this->nsid,
            'username' => $this->username,
            'correlation_id' => $this->correlationId,
        ];
    }
}
