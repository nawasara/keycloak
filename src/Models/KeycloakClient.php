<?php

namespace Nawasara\Keycloak\Models;

use Illuminate\Database\Eloquent\Model;
use Nawasara\Sync\Concerns\HasSyncStatus;

class KeycloakClient extends Model
{
    use HasSyncStatus;

    protected $table = 'nawasara_keycloak_clients';

    protected $fillable = [
        'client_uuid', 'client_id', 'name', 'description',
        'protocol', 'enabled', 'public_client',
        'service_accounts_enabled', 'standard_flow_enabled', 'direct_access_grants_enabled',
        'root_url', 'base_url', 'redirect_uris', 'web_origins',
        'sync_status', 'sync_error', 'last_synced_at',
        'content_hash',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'public_client' => 'boolean',
        'service_accounts_enabled' => 'boolean',
        'standard_flow_enabled' => 'boolean',
        'direct_access_grants_enabled' => 'boolean',
        'redirect_uris' => 'array',
        'web_origins' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function computeContentHash(): string
    {
        return hash('sha256', json_encode([
            'client_id' => $this->client_id,
            'enabled' => $this->enabled,
            'protocol' => $this->protocol,
            'public_client' => $this->public_client,
        ]));
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) return $query;
        $term = '%'.$term.'%';
        return $query->where(function ($q) use ($term) {
            $q->where('client_id', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
    }
}
