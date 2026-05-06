<?php

namespace Nawasara\Keycloak\Livewire\User;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Keycloak\Repositories\KeycloakUserRepository;

class Index extends Component
{
    /**
     * Hero stats — di-derive di Index level (bukan di section/table) supaya
     * angka summary tidak ter-bias filter table (search/status). Stats selalu
     * mencerminkan total realitas, table di bawahnya boleh ter-filter.
     *
     * Marked Computed → otomatis cached per request, tapi tidak di-cache antar
     * request. Page Users tidak di-poll, jadi cost satu query agregasi per
     * page-load acceptable.
     */
    #[Computed]
    public function stats(): array
    {
        $s = (new KeycloakUserRepository())->stats();

        // 2FA adoption rate — relevan ke active user, bukan total (karena
        // user disabled tidak relevan untuk security KPI).
        $totpRate = $s['enabled'] > 0
            ? round(($s['totp'] / $s['enabled']) * 100)
            : 0;

        return [
            ['label' => 'Total Users', 'value' => number_format($s['total']), 'icon' => 'lucide-users', 'color' => 'primary'],
            ['label' => 'Aktif', 'value' => number_format($s['enabled']), 'icon' => 'lucide-circle-check', 'color' => 'success'],
            ['label' => 'Disabled', 'value' => number_format($s['disabled']), 'icon' => 'lucide-ban', 'color' => 'neutral'],
            ['label' => '2FA Aktif', 'value' => number_format($s['totp']), 'icon' => 'lucide-shield-check', 'color' => 'info', 'description' => $totpRate.'% dari user aktif'],
        ];
    }

    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.user.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
