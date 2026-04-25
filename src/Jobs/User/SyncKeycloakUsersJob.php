<?php

namespace Nawasara\Keycloak\Jobs\User;

use Nawasara\Keycloak\Models\KeycloakUser;
use Nawasara\Keycloak\Services\KeycloakClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

/**
 * Full sync semua Keycloak users → DB snapshot.
 * Pakai pagination (default 100 per batch).
 */
class SyncKeycloakUsersJob extends AbstractSyncJob
{
    public int $timeout = 600;

    protected function service(): string
    {
        return 'keycloak';
    }

    protected function action(): string
    {
        return 'sync_users';
    }

    protected function targetType(): ?string
    {
        return 'KeycloakUser';
    }

    protected function targetId(): ?string
    {
        return null;
    }

    protected function execute(): array
    {
        $kc = app(KeycloakClient::class);

        if (! $kc->isConfigured()) {
            throw new \RuntimeException('Keycloak client is not configured');
        }

        $batchSize = 100;
        $first = 0;
        $allUsers = [];

        do {
            $batch = $kc->getUsers(['first' => $first, 'max' => $batchSize]);
            $allUsers = array_merge($allUsers, $batch);

            $count = count($batch);
            $first += $count;
        } while ($count === $batchSize);

        $stats = [
            'total' => count($allUsers),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deactivated' => 0,
        ];

        $seenIds = [];

        foreach ($allUsers as $row) {
            $userId = $row['id'] ?? null;
            if (! $userId) continue;

            $seenIds[] = $userId;

            $attrs = [
                'user_id' => $userId,
                'username' => $row['username'] ?? '',
                'email' => $row['email'] ?? null,
                'first_name' => $row['firstName'] ?? null,
                'last_name' => $row['lastName'] ?? null,
                'enabled' => (bool) ($row['enabled'] ?? false),
                'email_verified' => (bool) ($row['emailVerified'] ?? false),
                'totp' => (bool) ($row['totp'] ?? false),
                'attributes' => $row['attributes'] ?? null,
                'required_actions' => $row['requiredActions'] ?? null,
                'kc_created_at' => isset($row['createdTimestamp'])
                    ? \Carbon\Carbon::createFromTimestampMs((int) $row['createdTimestamp'])
                    : null,
                'sync_status' => KeycloakUser::SYNC_SYNCED,
                'sync_error' => null,
                'last_synced_at' => now(),
            ];

            $existing = KeycloakUser::where('user_id', $userId)->first();

            if ($existing) {
                $tempModel = new KeycloakUser(array_merge($existing->toArray(), $attrs));
                $newHash = $tempModel->computeContentHash();

                if ($existing->content_hash === $newHash && $existing->isSynced()) {
                    $stats['unchanged']++;
                    continue;
                }

                $existing->update(array_merge($attrs, ['content_hash' => $newHash]));
                $stats['updated']++;
            } else {
                $tempModel = new KeycloakUser($attrs);
                $newHash = $tempModel->computeContentHash();
                KeycloakUser::create(array_merge($attrs, ['content_hash' => $newHash]));
                $stats['created']++;
            }
        }

        // Users hilang dari Keycloak = di-delete dari DB
        if (! empty($seenIds)) {
            $stats['deactivated'] = KeycloakUser::whereNotIn('user_id', $seenIds)->delete();
        }

        return $stats;
    }
}
