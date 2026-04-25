<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_keycloak_clients', function (Blueprint $table) {
            $table->id();

            $table->string('client_uuid', 64)->unique();    // Keycloak internal UUID (id field)
            $table->string('client_id', 255)->index();      // String identifier (clientId field)
            $table->string('name', 255)->nullable();
            $table->text('description')->nullable();

            $table->string('protocol', 32)->nullable();     // 'openid-connect', 'saml'
            $table->boolean('enabled')->default(true);
            $table->boolean('public_client')->default(false);
            $table->boolean('service_accounts_enabled')->default(false);
            $table->boolean('standard_flow_enabled')->default(true);
            $table->boolean('direct_access_grants_enabled')->default(false);

            $table->string('root_url', 500)->nullable();
            $table->string('base_url', 500)->nullable();
            $table->json('redirect_uris')->nullable();
            $table->json('web_origins')->nullable();

            // Sync tracking
            $table->string('sync_status', 32)->default('synced');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('content_hash', 64)->nullable();

            $table->timestamps();

            $table->index('sync_status');
            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_keycloak_clients');
    }
};
