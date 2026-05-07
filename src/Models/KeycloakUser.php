<?php

namespace Nawasara\Keycloak\Models;

use Illuminate\Database\Eloquent\Model;
use Nawasara\Sync\Concerns\HasSyncStatus;

class KeycloakUser extends Model
{
    use HasSyncStatus;

    protected $table = 'nawasara_keycloak_users';

    protected $fillable = [
        'user_id', 'username', 'email', 'first_name', 'last_name',
        'enabled', 'email_verified', 'totp',
        'attributes', 'required_actions',
        'kc_created_at',
        'sync_status', 'sync_error', 'last_synced_at',
        'content_hash',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'email_verified' => 'boolean',
        'totp' => 'boolean',
        'attributes' => 'array',
        'required_actions' => 'array',
        'kc_created_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function computeContentHash(): string
    {
        return hash('sha256', json_encode([
            'username' => $this->username,
            'email' => $this->email,
            'enabled' => $this->enabled,
            'email_verified' => $this->email_verified,
        ]));
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) return $query;
        $term = '%'.$term.'%';
        return $query->where(function ($q) use ($term) {
            $q->where('username', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term);
        });
    }

    /**
     * Polymorphic enabled/disabled filter. Accepts string for single match
     * ('enabled' / 'disabled') or array for multi-select. The boolean
     * `enabled` column underlies the semantic value; selecting BOTH
     * 'enabled' and 'disabled' produces no constraint (every row matches).
     *
     * @param  string|array<int,string>|null  $status
     */
    public function scopeStatus($query, string|array|null $status)
    {
        if (empty($status)) {
            return $query;
        }

        $values = is_array($status) ? $status : [$status];
        $wantEnabled = in_array('enabled', $values, true);
        $wantDisabled = in_array('disabled', $values, true);

        if ($wantEnabled && ! $wantDisabled) {
            return $query->where('enabled', true);
        }
        if ($wantDisabled && ! $wantEnabled) {
            return $query->where('enabled', false);
        }
        // Both selected, neither matched the dictionary, etc → no-op.
        return $query;
    }
}
