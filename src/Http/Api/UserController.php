<?php

namespace Nawasara\Keycloak\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\Keycloak\Http\Resources\KeycloakUserResource;
use Nawasara\Keycloak\Models\KeycloakUser;

/**
 * Public API direktori pegawai, dibaca dari snapshot Keycloak lokal
 * (`nawasara_keycloak_users`) — BUKAN dari Keycloak Admin API langsung.
 *
 * Alasannya penting: memanggil Keycloak per-request akan menjadikan realm
 * sebagai titik kegagalan tunggal bagi setiap konsumen API, dan membebani
 * server auth dengan trafik baca yang bisa dilayani dari cache. Snapshot
 * di-refresh tiap jam oleh SyncKeycloakUsersJob; konsumen yang butuh data
 * detik-terkini memang bukan sasaran endpoint ini.
 *
 * Auth + scope dicek di middleware:
 *   - api.auth → token valid
 *   - scope:keycloak.user.read → list + detail
 *
 * Read-only secara sengaja. Aksi tulis (enable/disable, reset password) tetap
 * lewat UI Nawasara yang punya audit log dan sudo gating.
 */
class UserController extends Controller
{
    /**
     * GET /api/v1/keycloak/users
     * Scope: keycloak.user.read
     *
     * Query params:
     *   q        — cari di username / email / nama depan / nama belakang
     *   status   — enabled (default) | disabled | all
     *   per_page — 1..100, default 50
     */
    public function index(Request $request): JsonResponse
    {
        $query = KeycloakUser::query()->orderBy('username');

        // Default `enabled`: konsumen hampir selalu memaksudkan pegawai aktif,
        // dan memasukkan akun nonaktif diam-diam ke hasil pencarian orang
        // adalah cara mudah membuat mereka salah tampil di UI konsumen.
        $status = (string) $request->query('status', 'enabled');
        if ($status === 'enabled') {
            $query->where('enabled', true);
        } elseif ($status === 'disabled') {
            $query->where('enabled', false);
        }
        // status=all → tanpa filter.

        if ($q = trim((string) $request->query('q', ''))) {
            $query->search($q);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $users = $query->paginate($perPage);

        return response()->json([
            'data' => KeycloakUserResource::collection($users->items())->resolve(),
            'meta' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/keycloak/users/{id}
     * Scope: keycloak.user.read
     *
     * `id` = UUID Keycloak (sub), identifier stabil yang juga dikembalikan
     * di field `id` pada endpoint index.
     */
    public function show(string $id): JsonResponse
    {
        $user = KeycloakUser::where('user_id', $id)->first();

        if (! $user) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'User tidak ditemukan di direktori.',
            ], 404);
        }

        return response()->json([
            'data' => (new KeycloakUserResource($user))->resolve(request()),
        ]);
    }

    /**
     * GET /api/v1/keycloak/users/by-username/{username}
     * Scope: keycloak.user.read
     *
     * Jalur pencarian langsung untuk konsumen yang memegang username (mis. NIP)
     * tapi belum pernah menyimpan UUID-nya. Tanpa ini mereka harus memanggil
     * index dengan `q=` lalu menebak baris mana yang cocok — pencocokan yang
     * lebih baik dilakukan di sini, sekali, dengan benar.
     */
    public function showByUsername(string $username): JsonResponse
    {
        $user = KeycloakUser::where('username', $username)->first();

        if (! $user) {
            return response()->json([
                'error'   => 'not_found',
                'message' => 'User tidak ditemukan di direktori.',
            ], 404);
        }

        return response()->json([
            'data' => (new KeycloakUserResource($user))->resolve(request()),
        ]);
    }
}
