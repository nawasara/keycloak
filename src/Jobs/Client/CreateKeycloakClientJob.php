<?php

namespace Nawasara\Keycloak\Jobs\Client;

class CreateKeycloakClientJob extends AbstractKeycloakClientJob
{
    protected function action(): string
    {
        return 'client_create';
    }

    protected function shouldCheckConflict(): bool
    {
        return false;
    }

    protected function targetId(): ?string
    {
        return $this->payload['client_id'] ?? null;
    }

    protected function execute(): array
    {
        $payload = $this->payload;

        $ok = $this->client()->createClient($payload);
        if (! $ok) {
            throw new \RuntimeException('Keycloak rejected create client');
        }

        // Trigger sync to pull the new client's UUID
        SyncKeycloakClientsJob::dispatch(triggerSource: 'event');

        return ['client_id' => $payload['clientId'] ?? $payload['client_id'] ?? null];
    }
}
