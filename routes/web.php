<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Keycloak\Livewire\User\Index as UserIndex;
use Nawasara\Keycloak\Livewire\Session\Index as SessionIndex;
use Nawasara\Keycloak\Livewire\EventLog\Index as EventLogIndex;

Route::middleware(['web', 'auth'])->prefix('nawasara-keycloak')->group(function () {
    Route::get('users', UserIndex::class)->name('nawasara-keycloak.user.index');
    Route::get('sessions', SessionIndex::class)->name('nawasara-keycloak.session.index');
    Route::get('events', EventLogIndex::class)->name('nawasara-keycloak.event.index');
});
