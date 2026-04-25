<?php

namespace Nawasara\Keycloak\Jobs\Client;

use Nawasara\Keycloak\Models\KeycloakClient;
use Nawasara\Keycloak\Services\KeycloakClient as KcClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

class SyncKeycloakClientsJob extends AbstractSyncJob
{
    public int $timeout = 180;

    protected function service(): string
    {
        return 'keycloak';
    }

    protected function action(): string
    {
        return 'sync_clients';
    }

    protected function targetType(): ?string
    {
        return 'KeycloakClient';
    }

    protected function targetId(): ?string
    {
        return null;
    }

    protected function execute(): array
    {
        $kc = app(KcClient::class);

        if (! $kc->isConfigured()) {
            throw new \RuntimeException('Keycloak client is not configured');
        }

        $clients = $kc->getClients();

        // Filter out internal clients
        $internals = ['account', 'account-console', 'admin-cli', 'broker', 'realm-management', 'security-admin-console'];
        $clients = array_filter($clients, fn ($c) => ! in_array($c['clientId'] ?? '', $internals));

        $stats = [
            'total' => count($clients),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deactivated' => 0,
        ];

        $seenUuids = [];

        foreach ($clients as $row) {
            $uuid = $row['id'] ?? null;
            if (! $uuid) continue;

            $seenUuids[] = $uuid;

            $attrs = [
                'client_uuid' => $uuid,
                'client_id' => $row['clientId'] ?? '',
                'name' => $row['name'] ?? null,
                'description' => $row['description'] ?? null,
                'protocol' => $row['protocol'] ?? null,
                'enabled' => (bool) ($row['enabled'] ?? false),
                'public_client' => (bool) ($row['publicClient'] ?? false),
                'service_accounts_enabled' => (bool) ($row['serviceAccountsEnabled'] ?? false),
                'standard_flow_enabled' => (bool) ($row['standardFlowEnabled'] ?? true),
                'direct_access_grants_enabled' => (bool) ($row['directAccessGrantsEnabled'] ?? false),
                'root_url' => $row['rootUrl'] ?? null,
                'base_url' => $row['baseUrl'] ?? null,
                'redirect_uris' => $row['redirectUris'] ?? null,
                'web_origins' => $row['webOrigins'] ?? null,
                'sync_status' => KeycloakClient::SYNC_SYNCED,
                'sync_error' => null,
                'last_synced_at' => now(),
            ];

            $existing = KeycloakClient::where('client_uuid', $uuid)->first();

            if ($existing) {
                $tempModel = new KeycloakClient(array_merge($existing->toArray(), $attrs));
                $newHash = $tempModel->computeContentHash();

                if ($existing->content_hash === $newHash && $existing->isSynced()) {
                    $stats['unchanged']++;
                    continue;
                }

                $existing->update(array_merge($attrs, ['content_hash' => $newHash]));
                $stats['updated']++;
            } else {
                $tempModel = new KeycloakClient($attrs);
                $newHash = $tempModel->computeContentHash();
                KeycloakClient::create(array_merge($attrs, ['content_hash' => $newHash]));
                $stats['created']++;
            }
        }

        if (! empty($seenUuids)) {
            $stats['deactivated'] = KeycloakClient::whereNotIn('client_uuid', $seenUuids)
                ->where('sync_status', '!=', KeycloakClient::SYNC_PENDING_DELETE)
                ->where('sync_status', '!=', KeycloakClient::SYNC_PENDING_CREATE)
                ->delete();
        }

        return $stats;
    }
}
