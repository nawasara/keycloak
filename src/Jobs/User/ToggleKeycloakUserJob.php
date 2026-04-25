<?php

namespace Nawasara\Keycloak\Jobs\User;

class ToggleKeycloakUserJob extends AbstractKeycloakUserJob
{
    protected function action(): string
    {
        return 'user_toggle';
    }

    protected function execute(): array
    {
        $userId = $this->payload['user_id'];
        $enabled = (bool) $this->payload['enabled'];

        $record = $this->record();
        if (! $record) {
            throw new \RuntimeException("User not found: {$userId}");
        }

        $ok = $enabled
            ? $this->client()->enableUser($userId)
            : $this->client()->disableUser($userId);

        if (! $ok) {
            throw new \RuntimeException('Keycloak rejected toggle');
        }

        $record->fill(['enabled' => $enabled]);
        $record->content_hash = $record->computeContentHash();
        $record->markSynced();
        $record->save();

        return ['user_id' => $userId, 'enabled' => $enabled];
    }
}
