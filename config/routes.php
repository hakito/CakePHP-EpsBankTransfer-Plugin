<?php

use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

Router::plugin(
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