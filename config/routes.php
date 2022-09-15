<?php

use Cake\Routing\RouteBuilder;

/** @var \Cake\Routing\RouteBuilder $routes */
$routes->plugin(
    'EpsBankTransfer',
    ['path' => '/eps_bank_transfer'],
    function (RouteBuilder $routes)
    {
        $routes->post('/process/:eRemittanceIdentifier',
        [
            'controller' => 'PaymentNotifications',
            'action' => 'process'
        ])
            ->setPass(['eRemittanceIdentifier'])
        ;
    }
);