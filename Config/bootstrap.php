<?php
/**
 * BEGIN EpsBankTransfer Configuration
 * Use these settings to set defaults for the EPS component.
 *
 * put this code into your bootstrap.php, so you can override settings.
 */
if (is_null(Configure::read('EpsBankTransfer'))) {
	Configure::write('EpsBankTransfer', array(
                // required parameters
		'userid'        => 'AKLJS231534',                       // Eps "Händler" id
		'secret'        => 'topSecret',                         // Secret for authentication
                'iban'          => 'AT611904300234573201',              // IBAN code of bank account where money will be sent to
		'bic'           => 'GAWIATW1XXX',                       // BIC code of bank account where money will be sent to
		'account_owner' => 'John Q. Public',                    // Name of the account owner where money will be sent to
            
                //// optional parameters
                //'SecuritySuffixLength' => 8,                            // Number of hash chars appended to remittance identifier
                //'SecuritySeed'  => Configure::read('Security.salt'),    // Hash seed or suffix of remittance identifier
                //'ConfirmationCallback' => 'afterEpsBankTransferNotification', // Name of callback function to be called in app controller when confirmation url is called with bankconfirmation details
                //'VitalityCheckCallback' => null                         // Name of callback function to be called when confirmation url is called with vitalitycheck details
       ));
}

/** END EpsBankTransfer Configuration */


if (!defined('EPS_BANK_TRANSFER_APP')) {
	define('EPS_BANK_TRANSFER_APP', dirname(__DIR__) . DS);
}

require_once EPS_BANK_TRANSFER_APP . 'Lib' . DS . 'EpsCommon.php';
require_once EPS_BANK_TRANSFER_APP . 'Lib' . DS . 'EPS' . DS . 'src' . DS . 'autoloader.php';