<?php

use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

Router::plugin(
    'EpsBankTransfer',
    ['path' => '/eps_bank_transfer'],
    function (RouteBuilder $routes) {		
        $routes->get('/process/*', [
            'controller' => 'PaymentNotifications',
            'action' => 'process']);
    }
);