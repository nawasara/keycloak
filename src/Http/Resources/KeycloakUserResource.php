<?php

namespace Nawasara\Keycloak\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\Keycloak\Models\KeycloakUser;

/**
 * Transformer user Keycloak untuk public API — **direktori pegawai**, bukan
 * cerminan penuh record Keycloak.
 *
 * Field di-allow-list secara eksplisit. Yang sengaja DIBLOK dan alasannya:
 *
 *   - `whatsapp_number` → nomor pribadi; tidak ada konsumen API yang butuh,
 *     dan sekali keluar tidak bisa ditarik kembali.
 *   - `attributes` (blob mentah) → wadah bebas yang isinya bisa bertambah
 *     kapan saja lewat konfigurasi Keycloak. Meng-expose blob = meng-expose
 *     field yang belum ada hari ini tanpa keputusan sadar.
 *   - `required_actions`, `totp`, sesi → status keamanan akun. Bocor berarti
 *     memberi peta siapa yang belum memasang 2FA.
 *   - `sync_status`, `sync_error`, `content_hash` → internal Nawasara, tidak
 *     bermakna bagi konsumen.
 *
 * `user_id` (UUID Keycloak / sub) sengaja DIIKUTKAN: itulah kunci stabil untuk
 * menautkan orang lintas aplikasi — sama peran seperti `slug` di CameraResource.
 * UUID bukan rahasia; ia identifier, bukan kredensial.
 *
 * @mixin KeycloakUser
 */
class KeycloakUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Identifier stabil lintas aplikasi. Tidak berubah saat username
            // atau email diganti — inilah alasannya di-expose.
            'id' => $this->user_id,
            'username' => $this->username,

            // Nama lengkap bergelar dari atribut `fullName` kalau ada, jatuh
            // ke first+last. Accessor sudah menangani keduanya.
            'name' => $this->full_name ?: ($this->username ?? null),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,

            // NIP: identitas kepegawaian resmi. Dipakai konsumen untuk
            // mencocokkan pegawai dengan sistem lain (SIMPEG, dsb).
            'nip' => $this->nip,

            'email' => $this->email,
            'email_verified' => (bool) $this->email_verified,

            // Status akun — konsumen perlu tahu untuk menyaring pegawai
            // yang sudah tidak aktif.
            'enabled' => (bool) $this->enabled,

            // Kapan akun dibuat di Keycloak. Berguna untuk sinkronisasi
            // inkremental di sisi konsumen.
            'created_at' => $this->kc_created_at?->toIso8601String(),
        ];
    }
}
