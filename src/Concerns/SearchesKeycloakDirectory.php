<?php

namespace Nawasara\Keycloak\Concerns;

use Nawasara\Keycloak\Models\KeycloakUser;
use Nawasara\Keycloak\Support\KeycloakUserProvisioner;

/**
 * Pencarian orang di direktori Keycloak untuk komponen Livewire.
 *
 * Dipakai di mana pun UI perlu memilih ORANG, bukan sekadar user yang kebetulan
 * sudah ada di tabel `users`. Bedanya penting: tabel lokal hanya berisi orang
 * yang pernah login atau pernah ditambahkan manual, jadi memilih dari sana
 * membuat pegawai yang belum pernah membuka Nawasara tidak bisa dipilih sama
 * sekali.
 *
 * Komponen pemakai cukup menyediakan properti pencarian dan memanggil
 * `keycloakSearchResults()`. Hasilnya di-index numerik dan pemilihan dilakukan
 * lewat index, bukan mengoper username lewat DOM — lihat CLAUDE.md 13.f.
 *
 * Usage:
 *   use SearchesKeycloakDirectory;
 *   public string $userSearch = '';
 *
 *   public function pick(int $index): void {
 *       $kc = $this->keycloakUserAt($index, $this->userSearch);
 *       $user = $this->provisioner()->fromSnapshot($kc);
 *   }
 */
trait SearchesKeycloakDirectory
{
    /**
     * Cari orang di snapshot Keycloak.
     *
     * @param  array<int,string>  $excludeUsernames  username yang disembunyikan (lowercase)
     * @param  array<int,string>  $excludeEmails     email yang disembunyikan (lowercase)
     * @return array<int, array{kc_id:string, kc_username:?string, name:string, nip:?string, email:?string, is_local:bool}>
     */
    public function keycloakSearchResults(
        string $term,
        array $excludeUsernames = [],
        array $excludeEmails = [],
        int $limit = 15,
    ): array {
        $term = trim($term);

        if (mb_strlen($term) < $this->keycloakSearchMinLength()) {
            return [];
        }

        $localModel = config('auth.providers.users.model');

        // Ambil lebih banyak dari yang ditampilkan — sebagian akan tersaring
        // oleh daftar exclude di bawah.
        $candidates = KeycloakUser::query()
            ->search($term)
            ->where('enabled', true)
            ->limit(max($limit * 3, 40))
            ->get()
            ->reject(function (KeycloakUser $kc) use ($excludeUsernames, $excludeEmails) {
                $username = mb_strtolower((string) $kc->username);
                $email    = mb_strtolower((string) $kc->email);

                return ($username !== '' && in_array($username, $excludeUsernames, true))
                    || ($email !== '' && in_array($email, $excludeEmails, true));
            })
            ->take($limit)
            ->values();

        if ($candidates->isEmpty()) {
            return [];
        }

        // Tandai siapa yang sudah punya baris lokal, supaya UI bisa membedakan
        // "sudah terdaftar" dari "akan dibuatkan akun".
        $known = $localModel::query()
            ->whereIn('keycloak_id', $candidates->pluck('user_id')->filter()->all())
            ->orWhereIn('username', $candidates->pluck('username')->filter()->all())
            ->get(['keycloak_id', 'username']);

        $knownIds       = $known->pluck('keycloak_id')->filter()->all();
        $knownUsernames = $known->pluck('username')->filter()->map(fn ($u) => mb_strtolower($u))->all();

        return $candidates
            ->map(fn (KeycloakUser $kc) => [
                'kc_id'       => $kc->user_id,
                'kc_username' => $kc->username,
                'name'        => $kc->full_name ?: ($kc->username ?? '—'),
                'nip'         => $kc->nip,
                'email'       => $kc->email,
                'is_local'    => in_array($kc->user_id, $knownIds, true)
                    || in_array(mb_strtolower((string) $kc->username), $knownUsernames, true),
            ])
            ->all();
    }

    /**
     * Resolve satu baris hasil pencarian (by index) jadi model KeycloakUser.
     *
     * Pencarian dijalankan ulang di server memakai term yang sama, jadi index
     * yang dikirim dari DOM tidak pernah dipercaya sebagai identitas — hanya
     * sebagai posisi di hasil yang baru saja dihitung ulang.
     *
     * @param  array<int,string>  $excludeUsernames
     * @param  array<int,string>  $excludeEmails
     */
    public function keycloakUserAt(
        int $index,
        string $term,
        array $excludeUsernames = [],
        array $excludeEmails = [],
    ): ?KeycloakUser {
        $results = $this->keycloakSearchResults($term, $excludeUsernames, $excludeEmails);

        $kcId = $results[$index]['kc_id'] ?? null;

        return $kcId ? KeycloakUser::where('user_id', $kcId)->first() : null;
    }

    protected function provisioner(): KeycloakUserProvisioner
    {
        return app(KeycloakUserProvisioner::class);
    }

    protected function keycloakSearchMinLength(): int
    {
        return 2;
    }
}
