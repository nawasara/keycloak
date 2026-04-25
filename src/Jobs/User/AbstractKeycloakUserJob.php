<?php

namespace Nawasara\Keycloak\Jobs\User;

use Nawasara\Keycloak\Models\KeycloakUser;
use Nawasara\Keycloak\Services\KeycloakClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

abstract class AbstractKeycloakUserJob extends AbstractSyncJob
{
    public int $timeout = 60;

    protected function service(): string
    {
        return 'keycloak';
    }

    protected function targetType(): ?string
    {
        return 'KeycloakUser';
    }

    protected function targetId(): ?string
    {
        return $this->payload['user_id'] ?? null;
    }

    protected function client(): KeycloakClient
    {
        return app(KeycloakClient::class);
    }

    protected function record(): ?KeycloakUser
    {
        $userId = $this->payload['user_id'] ?? null;
        if (! $userId) return null;
        return KeycloakUser::where('user_id', $userId)->first();
    }

    protected function currentExternalHash(): ?string
    {
        return $this->record()?->content_hash;
    }
}
