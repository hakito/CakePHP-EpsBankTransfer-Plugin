<?php

$baseTestFile = join(DS, array(APP, 'Vendor', 'hakito', 'php-stuzza-eps-banktransfer', 'tests', 'unit', 'at', 'externet', 'eps_bank_transfer', 'BaseTest.php'));
if (file_exists($baseTestFile))
    require_once $baseTestFile;

// for travic-ci tests
$baseTestFile = join(DS, array(dirname(__DIR__), 'vendor', 'hakito', 'php-stuzza-eps-banktransfer', 'tests', 'unit', 'at', 'externet', 'eps_bank_transfer', 'BaseTest.php'));
if (file_exists($baseTestFile))
    require_once $baseTestFile;
unset($baseTestFile);
