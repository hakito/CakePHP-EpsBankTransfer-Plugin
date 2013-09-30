<?php
/**
 * BEGIN EpsBankTransfer Configuration
 * Use these settings to set defaults for the EpsHelper class.
 *
 * put this code into your bootstrap.php, so you can override settings.
 */
if (is_null(Configure::read('EpsBankTransfer'))) {
	Configure::write('EpsBankTransfer', array(
		'userid'        => 'AKLJS231534',           // Eps "Händler" id
		'secret'        => 'topSecret',             // Secret for authentication
                'iban'          => 'AT611904300234573201',  // IBAN code of bank account where money will be sent to
		'bic'           => 'GAWIATW1XXX',           // BIC code of bank account where money will be sent to
		'account_owner' => 'John Q. Public',        // Name of the account owner where money will be sent to
	));
}

/** END EpsBankTransfer Configuration */


if (!defined('EPS_BANK_TRANSFER_APP')) {
	define('EPS_BANK_TRANSFER_APP', dirname(__DIR__) . DS);
}

require_once EPS_BANK_TRANSFER_APP . 'Lib' . DS . 'EPS' . DS . 'src' . DS . 'autoloader.php';