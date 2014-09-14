<?php

class LibTest extends CakeTestCase
{
    public static function suite() {
        $suite = new CakeTestSuite('Lib tests');
        $suite->addTestDirectoryRecursive(join(DS, array(APP, 'Vendor', 'hakito', 'php-stuzza-eps-banktransfer', 'tests')));
        return $suite;
    }
}
