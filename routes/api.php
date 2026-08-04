<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Keycloak\Http\Api\UserController;

/*
|--------------------------------------------------------------------------
| Keycloak API routes
|--------------------------------------------------------------------------
| Di-mount oleh KeycloakServiceProvider di prefix /api/v1/keycloak dengan
| middleware group: api + api.auth + api.log.
|
| Read-only. Data direktori pegawai dilayani dari snapshot lokal; aksi tulis
| terhadap realm sengaja tidak diekspos lewat API.
*/

Route::middleware('scope:keycloak.user.read')->group(function () {
    Route::get('/users', [UserController::class, 'index'])
        ->name('users.index');

    // by-username didaftarkan SEBELUM /users/{id} — kalau dibalik, segmen
    // "by-username" akan tertangkap sebagai {id} dan selalu 404.
    Route::get('/users/by-username/{username}', [UserController::class, 'showByUsername'])
        ->name('users.show-by-username');

    Route::get('/users/{id}', [UserController::class, 'show'])
        ->name('users.show');
});
