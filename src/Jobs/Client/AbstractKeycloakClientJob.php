<?php

namespace Nawasara\Keycloak\Jobs\Client;

use Nawasara\Keycloak\Models\KeycloakClient as KcModel;
use Nawasara\Keycloak\Services\KeycloakClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

abstract class AbstractKeycloakClientJob extends AbstractSyncJob
{
    public int $timeout = 60;

    protected function service(): string
    {
        return 'keycloak';
    }

    protected function targetType(): ?string
    {
        return 'KeycloakClient';
    }

    protected function targetId(): ?string
    {
        return $this->payload['client_uuid'] ?? null;
    }

    protected function client(): KeycloakClient
    {
        return app(KeycloakClient::class);
    }

    protected function record(): ?KcModel
    {
        $uuid = $this->payload['client_uuid'] ?? null;
        if (! $uuid) return null;
        return KcModel::where('client_uuid', $uuid)->first();
    }

    protected function currentExternalHash(): ?string
    {
        return $this->record()?->content_hash;
    }
}
