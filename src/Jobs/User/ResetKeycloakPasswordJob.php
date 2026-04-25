<?php

namespace Nawasara\Keycloak\Jobs\User;

class ResetKeycloakPasswordJob extends AbstractKeycloakUserJob
{
    protected function action(): string
    {
        return 'user_reset_password';
    }

    protected function execute(): array
    {
        $userId = $this->payload['user_id'];
        $password = $this->payload['password'];
        $temporary = (bool) ($this->payload['temporary'] ?? true);

        $record = $this->record();
        if (! $record) {
            throw new \RuntimeException("User not found: {$userId}");
        }

        $ok = $this->client()->resetPassword($userId, $password, $temporary);
        if (! $ok) {
            throw new \RuntimeException('Keycloak rejected password reset');
        }

        // Password change tidak ubah content_hash fields (username/email/enabled)
        $record->markSynced();

        return ['user_id' => $userId];
    }
}
