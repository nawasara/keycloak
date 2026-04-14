<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Keycloak\Livewire\User\Index as UserIndex;
use Nawasara\Keycloak\Livewire\Client\Index as ClientIndex;
use Nawasara\Keycloak\Livewire\Session\Index as SessionIndex;
use Nawasara\Keycloak\Livewire\EventLog\Index as EventLogIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-keycloak')->group(function () {
    Route::get('users', UserIndex::class)
        ->middleware(PermissionMiddleware::using('keycloak.user.view'))
        ->name('nawasara-keycloak.user.index');

    Route::get('clients', ClientIndex::class)
        ->middleware(PermissionMiddleware::using('keycloak.client.view'))
        ->name('nawasara-keycloak.client.index');

    Route::get('sessions', SessionIndex::class)
        ->middleware(PermissionMiddleware::using('keycloak.session.view'))
        ->name('nawasara-keycloak.session.index');

    Route::get('events', EventLogIndex::class)
        ->middleware(PermissionMiddleware::using('keycloak.event.view'))
        ->name('nawasara-keycloak.event.index');
});
