<?php

namespace Nawasara\Keycloak\Livewire\Session;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.session.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
