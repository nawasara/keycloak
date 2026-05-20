<?php

namespace Nawasara\Keycloak\Console\Commands;

use Illuminate\Console\Command;
use Nawasara\Keycloak\Jobs\Client\SyncKeycloakClientsJob;
use Nawasara\Keycloak\Jobs\User\SyncKeycloakUsersJob;

/**
 * Sync Keycloak (users + clients) ke DB snapshot.
 *
 * Default: dispatch job ke queue (async). Scheduler memakai mode ini.
 * Flag --sync menjalankan job synchronously — untuk first run / debug.
 */
class SyncCommand extends Command
{
    protected $signature = 'keycloak:sync
                            {--users : Hanya sync users}
                            {--clients : Hanya sync clients}
                            {--sync : Jalankan synchronous (skip queue) — untuk debug}';

    protected $description = 'Sync Keycloak users + clients ke DB snapshot. Default: dispatch job ke queue.';

    public function handle(): int
    {
        $onlyUsers = (bool) $this->option('users');
        $onlyClients = (bool) $this->option('clients');
        $runSync = (bool) $this->option('sync');

        // Tanpa flag spesifik → sync keduanya.
        $doUsers = $onlyUsers || (! $onlyUsers && ! $onlyClients);
        $doClients = $onlyClients || (! $onlyUsers && ! $onlyClients);

        if ($doUsers) {
            $this->dispatchJob(SyncKeycloakUsersJob::class, 'users', $runSync);
        }

        if ($doClients) {
            $this->dispatchJob(SyncKeycloakClientsJob::class, 'clients', $runSync);
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string  $jobClass
     */
    protected function dispatchJob(string $jobClass, string $label, bool $runSync): void
    {
        $job = new $jobClass(triggerSource: 'scheduled');

        if ($runSync) {
            try {
                $job->handle();
                $this->line("  ✓ Sync {$label} done synchronously");
            } catch (\Throwable $e) {
                $this->error("  ✗ Sync {$label} failed: ".$e->getMessage());
            }

            return;
        }

        dispatch($job);
        $this->info("Dispatched Keycloak {$label} sync job to queue.");
    }
}
