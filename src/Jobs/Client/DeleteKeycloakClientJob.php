<?php

namespace Nawasara\Keycloak\Jobs\Client;

class DeleteKeycloakClientJob extends AbstractKeycloakClientJob
{
    protected function action(): string
    {
        return 'client_delete';
    }

    protected function execute(): array
    {
        $uuid = $this->payload['client_uuid'];

        $record = $this->record();

        $ok = $this->client()->deleteClient($uuid);
        if (! $ok) {
            throw new \RuntimeException("Keycloak rejected delete for {$uuid}");
        }

        $record?->delete();

        return ['client_uuid' => $uuid, 'deleted' => true];
    }
}
