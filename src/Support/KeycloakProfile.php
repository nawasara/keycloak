<?php

namespace Nawasara\Keycloak\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Nawasara\Keycloak\Models\KeycloakUser;

/**
 * Resolves a person's contact profile (name, NIP, WhatsApp, email) from the
 * Keycloak snapshot, given a Laravel User. This is the single source of truth
 * that replaces the old manually-typed PIC records across the app.
 *
 * There is no FK between users and nawasara_keycloak_users; they're matched by
 * username first, then email. Results are memoised per request.
 *
 * Usage:
 *   $p = KeycloakProfile::for($user);
 *   $p->name; $p->nip; $p->whatsapp; $p->email;
 */
class KeycloakProfile
{
    /** @var array<int|string,self> */
    private static array $cache = [];

    public function __construct(
        public readonly string $name,
        public readonly ?string $nip,
        public readonly ?string $whatsapp,
        public readonly ?string $email,
        public readonly bool $found,
    ) {
    }

    /**
     * Build a profile for a Laravel user (or anything with username/email).
     * Falls back to the user's own name/email when no Keycloak snapshot exists.
     */
    public static function for(?Authenticatable $user): self
    {
        if (! $user) {
            return new self('—', null, null, null, false);
        }

        $key = method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : spl_object_id($user);
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $username = $user->username ?? null;
        $email    = $user->email ?? null;

        $kc = null;
        if ($username) {
            $kc = KeycloakUser::where('username', $username)->first();
        }
        if (! $kc && $email) {
            $kc = KeycloakUser::where('email', $email)->first();
        }

        if ($kc) {
            $profile = new self(
                name: $kc->full_name ?: ($user->name ?? $username ?? '—'),
                nip: $kc->nip,
                whatsapp: $kc->whatsapp,
                email: $kc->email ?: $email,
                found: true,
            );
        } else {
            // No snapshot — use whatever the Laravel user carries.
            $profile = new self(
                name: $user->name ?? $username ?? '—',
                nip: null,
                whatsapp: null,
                email: $email,
                found: false,
            );
        }

        return self::$cache[$key] = $profile;
    }

    /** Bulk resolve for a set of user ids → [userId => KeycloakProfile]. */
    public static function forUserIds(array $userIds): array
    {
        $userModel = config('auth.providers.users.model');
        $users = $userModel::whereIn('id', array_unique($userIds))->get()->keyBy('id');

        $out = [];
        foreach ($userIds as $id) {
            $out[$id] = self::for($users->get($id));
        }
        return $out;
    }
}
