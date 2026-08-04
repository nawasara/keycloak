<?php

namespace Nawasara\Keycloak\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nawasara\Keycloak\Models\KeycloakUser;
use Spatie\Permission\Models\Role;

/**
 * Satu-satunya jalan resmi dari "orang di direktori Keycloak" ke "user Nawasara".
 *
 * Sebelumnya logika ini tersebar: SsoController punya versinya sendiri saat
 * login, dan halaman Zoom Meeting diam-diam punya salinan kedua yang bahkan
 * lupa memberi role — user tercipta tanpa hak akses apa pun. Semua penambahan
 * user dari Keycloak sekarang lewat sini.
 *
 * Aturan penautan:
 *   1. keycloak_id (Keycloak `sub`) — stabil terhadap rename, dipakai lebih dulu
 *   2. username
 *   3. email
 * Baris lokal yang ketemu lewat (2)/(3) tapi belum punya keycloak_id akan
 * di-adopsi — tautannya dikunci supaya rename berikutnya tidak memutusnya lagi.
 *
 * Usage:
 *   $user = app(KeycloakUserProvisioner::class)->fromSnapshot($kcUser);
 */
class KeycloakUserProvisioner
{
    /**
     * Pastikan ada user Nawasara untuk sebuah baris snapshot Keycloak.
     * Membuat user baru (dengan role default) kalau belum pernah ada.
     *
     * Idempoten: memanggil dua kali untuk orang yang sama mengembalikan
     * baris yang sama, tidak membuat duplikat.
     */
    public function fromSnapshot(KeycloakUser $kc): Authenticatable
    {
        return DB::transaction(function () use ($kc) {
            $user = $this->findLocal($kc->user_id, $kc->username, $kc->email);

            if ($user) {
                $this->adopt($user, $kc);

                return $user;
            }

            return $this->create(
                keycloakId: $kc->user_id,
                name: $kc->full_name ?: ($kc->username ?? 'SSO User'),
                username: $kc->username,
                email: $kc->email,
            );
        });
    }

    /**
     * Varian untuk jalur login SSO, yang datanya datang dari claim OIDC
     * (bukan dari tabel snapshot — user bisa saja login sebelum sync jalan).
     *
     * @param  array{id:?string, username:?string, email:?string, name:?string}  $claims
     */
    public function fromClaims(array $claims): Authenticatable
    {
        return DB::transaction(function () use ($claims) {
            $keycloakId = $claims['id'] ?? null;
            $username   = $claims['username'] ?? null;
            $email      = $claims['email'] ?? null;

            $user = $this->findLocal($keycloakId, $username, $email);

            if ($user) {
                $user->forceFill(array_filter([
                    'name'        => $claims['name'] ?? null,
                    'email'       => $email,
                    'keycloak_id' => $this->claimableKeycloakId($user, $keycloakId),
                ]))->save();

                return $user;
            }

            return $this->create(
                keycloakId: $keycloakId,
                name: $claims['name'] ?? $username ?? 'SSO User',
                username: $username,
                email: $email,
            );
        });
    }

    /**
     * Cari user lokal: keycloak_id dulu, lalu username, lalu email.
     * Mengembalikan null kalau orang ini belum pernah jadi user Nawasara.
     */
    public function findLocal(?string $keycloakId, ?string $username, ?string $email): ?Authenticatable
    {
        $model = $this->userModel();

        if ($keycloakId) {
            $user = $model::where('keycloak_id', $keycloakId)->first();
            if ($user) {
                return $user;
            }
        }

        if ($username) {
            $user = $model::where('username', $username)->first();
            if ($user) {
                return $user;
            }
        }

        if ($email) {
            return $model::where('email', $email)->first();
        }

        return null;
    }

    /**
     * Segarkan atribut user lokal dari snapshot Keycloak.
     *
     * Nama selalu ikut Keycloak. Email hanya diperbarui kalau tidak bentrok
     * dengan user lain — `users.email` unique, dan direktori Keycloak sesekali
     * memuat alamat kembar. Bentrok dilewati diam-diam di sini karena ini
     * dipanggil di tengah alur login/assign; menggagalkan seluruh operasi
     * gara-gara email kembar jauh lebih merugikan daripada nama yang basi.
     */
    public function refresh(Authenticatable $user, KeycloakUser $kc): void
    {
        $changes = [];

        $name = $kc->full_name;
        if ($name !== '' && $name !== $user->name) {
            $changes['name'] = $name;
        }

        if ($kc->email && $kc->email !== $user->email && ! $this->emailTakenByOther($kc->email, $user)) {
            $changes['email'] = $kc->email;
        }

        if ($changes !== []) {
            $user->forceFill($changes)->save();
        }
    }

    /**
     * Kunci tautan ke Keycloak untuk baris lokal yang ketemu lewat
     * username/email, lalu segarkan atributnya.
     */
    protected function adopt(Authenticatable $user, KeycloakUser $kc): void
    {
        $claimable = $this->claimableKeycloakId($user, $kc->user_id);

        if ($claimable !== null) {
            $user->forceFill(['keycloak_id' => $claimable])->save();
        }

        $this->refresh($user, $kc);
    }

    /**
     * Buat user Nawasara baru dari data Keycloak, lengkap dengan role default.
     *
     * Password diisi acak dan tidak pernah dipakai — login selalu lewat SSO.
     * Kolomnya sendiri sudah nullable, tapi mengisinya menutup kemungkinan
     * baris ini dipakai untuk login form seandainya auth_type berubah.
     */
    protected function create(?string $keycloakId, string $name, ?string $username, ?string $email): Authenticatable
    {
        $model = $this->userModel();

        $username = $username ?: ($email ? Str::before($email, '@') : null);

        $user = $model::create([
            'name'        => $name,
            'username'    => $username,
            'email'       => $email ?: ($username.'@sso.local'),
            'password'    => bcrypt(Str::random(40)),
            'auth_type'   => 'sso',
            'keycloak_id' => $keycloakId,
        ]);

        $this->assignDefaultRole($user);

        return $user;
    }

    /**
     * Beri role default (Setting `auth.sso.default_role`, biasanya `guest`)
     * supaya user hasil provisioning tidak berakhir tanpa hak akses apa pun.
     *
     * Role yang belum ter-seed dilewati — bukan alasan untuk menggagalkan
     * pembuatan user; admin bisa menetapkan role belakangan.
     */
    protected function assignDefaultRole(Authenticatable $user): void
    {
        $role = $this->defaultRole();

        if ($role === '' || ! method_exists($user, 'assignRole')) {
            return;
        }

        if (Role::where('name', $role)->exists()) {
            $user->assignRole($role);
        }
    }

    /**
     * Default role dibaca dari AuthMode kalau package core terpasang, supaya
     * tidak ada sumber kebenaran kedua. Fallback `guest` untuk instalasi tanpa
     * core.
     */
    protected function defaultRole(): string
    {
        $authMode = '\Nawasara\Core\Auth\AuthMode';

        if (class_exists($authMode) && method_exists($authMode, 'defaultSsoRole')) {
            return (string) $authMode::defaultSsoRole();
        }

        return 'guest';
    }

    /**
     * `keycloak_id` yang boleh ditulis ke user ini, atau null kalau tidak ada
     * yang perlu diubah.
     *
     * Menolak menimpa tautan yang sudah ada (dua identitas Keycloak berebut
     * satu user lokal — butuh keputusan manusia), dan menolak mengambil id
     * yang sudah dipegang user lokal lain (kolomnya unique; menulisnya akan
     * melempar constraint violation).
     */
    protected function claimableKeycloakId(Authenticatable $user, ?string $keycloakId): ?string
    {
        if (! $keycloakId || $user->keycloak_id) {
            return null;
        }

        $model = $this->userModel();

        $takenByOther = $model::where('keycloak_id', $keycloakId)
            ->where('id', '!=', $user->getAuthIdentifier())
            ->exists();

        return $takenByOther ? null : $keycloakId;
    }

    protected function emailTakenByOther(string $email, Authenticatable $user): bool
    {
        $model = $this->userModel();

        return $model::where('email', $email)
            ->where('id', '!=', $user->getAuthIdentifier())
            ->exists();
    }

    /** @return class-string */
    protected function userModel(): string
    {
        return config('auth.providers.users.model');
    }
}
