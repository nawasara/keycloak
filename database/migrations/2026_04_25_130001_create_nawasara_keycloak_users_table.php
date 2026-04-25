<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_keycloak_users', function (Blueprint $table) {
            $table->id();

            $table->string('user_id', 64)->unique();        // Keycloak user UUID
            $table->string('username', 255)->index();
            $table->string('email', 255)->nullable()->index();
            $table->string('first_name', 255)->nullable();
            $table->string('last_name', 255)->nullable();

            $table->boolean('enabled')->default(true);
            $table->boolean('email_verified')->default(false);
            $table->boolean('totp')->default(false);          // 2FA
            $table->json('attributes')->nullable();
            $table->json('required_actions')->nullable();

            $table->timestamp('kc_created_at')->nullable();   // createdTimestamp from KC

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
        Schema::dropIfExists('nawasara_keycloak_users');
    }
};
