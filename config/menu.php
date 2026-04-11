<?php

$prefix = 'nawasara-keycloak';

return [
    [
        'label' => 'Keycloak',
        'icon' => 'lucide-key-round',
        'url' => '',
        'permission' => 'keycloak.user.view',
        'submenu' => [
            [
                'label' => 'Users',
                'icon' => 'lucide-users',
                'url' => url($prefix.'/users'),
                'permission' => 'keycloak.user.view',
                'navigate' => true,
            ],
            [
                'label' => 'Sessions',
                'icon' => 'lucide-monitor-dot',
                'url' => url($prefix.'/sessions'),
                'permission' => 'keycloak.session.view',
                'navigate' => true,
            ],
            [
                'label' => 'Event Log',
                'icon' => 'lucide-scroll-text',
                'url' => url($prefix.'/events'),
                'permission' => 'keycloak.event.view',
                'navigate' => true,
            ],
        ],
    ],
];
