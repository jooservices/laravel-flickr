<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Console\Commands;

use Illuminate\Console\Command;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Events\FlickrOAuthRevoked;
use JOOservices\LaravelFlickr\Repositories\TokenRepository;

final class FlickrOAuthRevokeCommand extends Command
{
    protected $signature = 'flickr:oauth:revoke
                            {nsid : Flickr account NSID to disconnect}
                            {--connection= : Flickr API app connection name}';

    protected $description = 'Remove a stored Flickr OAuth token';

    public function handle(
        TokenRepository $tokens,
        RuntimeSettingsResolverInterface $runtime,
    ): int {
        $nsid = $this->argument('nsid');
        if (! is_string($nsid) || $nsid === '') {
            $this->error('A non-empty NSID is required.');

            return self::FAILURE;
        }

        $connection = $this->option('connection');
        $appName = is_string($connection) && $connection !== ''
            ? $connection
            : $runtime->defaultConnection();

        $tokens->forget($appName, $nsid);
        event(new FlickrOAuthRevoked($appName, $nsid));
        $this->info("Removed stored Flickr token for NSID [{$nsid}] on connection [{$appName}].");

        return self::SUCCESS;
    }
}
