<?php

/* Add route for handling payment notifications */
Router::connect('/eps_bank_transfer/process/**', array(
	'plugin' => 'eps_bank_transfer',
	'controller' => 'eps_payment_notifications',
	'action' => 'process'
));