<?php

namespace Nawasara\Keycloak\Livewire\Client;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.client.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
