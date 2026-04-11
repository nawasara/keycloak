<?php

namespace Nawasara\Keycloak\Livewire\User;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.user.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
