<?php

namespace Nawasara\Keycloak\Livewire\EventLog;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.event-log.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
