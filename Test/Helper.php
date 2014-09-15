<?php
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
