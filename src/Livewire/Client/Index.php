<?php

namespace Nawasara\Keycloak\Livewire\Client;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Keycloak\Repositories\KeycloakClientRepository;

class Index extends Component
{
    /**
     * Hero stats untuk Clients page.
     *
     * Pilihan KPI:
     * - Total / Aktif: kondisi inventaris
     * - Public client: visibility security — public client (SPA/native, no secret)
     *   lebih rawan karena tidak bisa simpan secret. Admin perlu tahu jumlahnya.
     * - Service Account: clients machine-to-machine (backend-to-backend), perlu
     *   monitoring tersendiri karena tidak ada user di-loop.
     */
    #[Computed]
    public function stats(): array
    {
        $s = (new KeycloakClientRepository())->stats();

        return [
            ['label' => 'Total Clients', 'value' => number_format($s['total']), 'icon' => 'lucide-app-window', 'color' => 'primary'],
            ['label' => 'Aktif', 'value' => number_format($s['enabled']), 'icon' => 'lucide-circle-check', 'color' => 'success'],
            ['label' => 'Public Client', 'value' => number_format($s['public']), 'icon' => 'lucide-globe', 'color' => 'info', 'description' => 'SPA / native (tanpa secret)'],
            ['label' => 'Service Account', 'value' => number_format($s['service_account']), 'icon' => 'lucide-bot', 'color' => 'warning', 'description' => 'Machine-to-machine'],
        ];
    }

    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.client.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
