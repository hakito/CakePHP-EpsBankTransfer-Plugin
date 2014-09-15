<?php
Configure::write('EpsBankTransfer', array(
    // required parameters
    'userid' => 'AKLJS231534', // Eps "Händler" id
    'secret' => 'topSecret', // Secret for authentication
    'iban' => 'AT611904300234573201', // IBAN code of bank account where money will be sent to
    'bic' => 'GAWIATW1XXX', // BIC code of bank account where money will be sent to
    'account_owner' => 'John Q. Public', // Name of the account owner where money will be sent to

    //// optional parameters
    //'ObscuritySuffixLength' => 8,                            // Number of hash chars appended to remittance identifier
    //'ObscuritySeed'  => Configure::read('Security.salt'),    // Hash seed or suffix of remittance identifier
    //'ConfirmationCallback' => 'afterEpsBankTransferNotification', // Name of callback function to be called in app controller when confirmation url is called with bankconfirmation details
    //'VitalityCheckCallback' => null                         // Name of callback function to be called when confirmation url is called with vitalitycheck details
));

$baseTestFile = join(DS, array(APP, 'Vendor', 'hakito', 'php-stuzza-eps-banktransfer', 'tests', 'unit', 'at', 'externet', 'eps_bank_transfer', 'BaseTest.php'));
if (!file_exists($baseTestFile))
{
    // for travic-ci tests
    $baseTestFile = join(DS, array(APP, 'vendor', 'hakito', 'php-stuzza-eps-banktransfer', 'tests', 'unit', 'at', 'externet', 'eps_bank_transfer', 'BaseTest.php'));
    require_once $baseTestFile;
}

if (file_exists($baseTestFile))
    require_once $baseTestFile;

unset($baseTestFile);
