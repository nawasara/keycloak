<?php

namespace Nawasara\Keycloak\Livewire\EventLog\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\Keycloak\Services\KeycloakClient;

class Table extends Component
{
    public string $typeFilter = '';
    public int $page = 0;
    public int $perPage = 25;

    protected KeycloakClient $keycloak;

    public function boot(KeycloakClient $keycloak)
    {
        $this->keycloak = $keycloak;
    }

    #[Computed]
    public function events()
    {
        $params = [
            'first' => $this->page * $this->perPage,
            'max' => $this->perPage,
        ];

        if ($this->typeFilter) {
            $params['type'] = $this->typeFilter;
        }

        return $this->keycloak->getEvents($params);
    }

    public function updatedTypeFilter()
    {
        $this->page = 0;
        unset($this->events);
    }

    public function previousPage()
    {
        $this->page = max(0, $this->page - 1);
        unset($this->events);
    }

    public function nextPage()
    {
        $this->page++;
        unset($this->events);
    }

    public function render()
    {
        return view('nawasara-keycloak::livewire.pages.event-log.section.table');
    }
}
