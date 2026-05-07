<?php

namespace Nawasara\Keycloak\Livewire\EventLog\Section;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\Keycloak\Services\KeycloakClient;
use Nawasara\Ui\Livewire\Concerns\HasTimeWindow;

class Table extends Component
{
    use HasTimeWindow;

    public string $typeFilter = '';
    public int $page = 0;
    public int $perPage = 25;

    protected KeycloakClient $keycloak;

    public function boot(KeycloakClient $keycloak)
    {
        $this->keycloak = $keycloak;
    }

    /**
     * Page reset is manual here - this component pre-dates WithPagination
     * (events come from a live Keycloak API call, not a DB query). The
     * trait's onTimeWindowChanged() expects resetPage(); we override so
     * window changes also rewind to first page.
     */
    protected function onTimeWindowChanged(): void
    {
        $this->page = 0;
        unset($this->events);
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

        // Keycloak admin API supports `dateFrom` / `dateTo` (Y-m-d) params,
        // so we resolve the trait's preset/custom window to bounds and
        // forward them. resolveTimeWindow returns Carbon instances; null
        // means "no constraint" (custom mode with empty endpoints).
        [$from, $to] = $this->resolveTimeWindow();
        if ($from) {
            $params['dateFrom'] = $from->toDateString();
        }
        if ($to) {
            $params['dateTo'] = $to->toDateString();
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
