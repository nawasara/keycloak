<?php

namespace Nawasara\Keycloak\Jobs\Client;

class UpdateKeycloakClientJob extends AbstractKeycloakClientJob
{
    protected function action(): string
    {
        return 'client_update';
    }

    protected function execute(): array
    {
        $uuid = $this->payload['client_uuid'];

        $record = $this->record();
        if (! $record) {
            throw new \RuntimeException("Client not found: {$uuid}");
        }

        // Strip our internal key
        $apiPayload = collect($this->payload)
            ->except(['client_uuid'])
            ->all();

        $ok = $this->client()->updateClient($uuid, $apiPayload);
        if (! $ok) {
            throw new \RuntimeException("Keycloak rejected update for {$uuid}");
        }

        // Trigger sync to refresh local fields
        SyncKeycloakClientsJob::dispatch(triggerSource: 'event');

        return ['client_uuid' => $uuid];
    }
}
