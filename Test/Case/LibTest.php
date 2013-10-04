<?php

class LibTest extends CakeTestCase
{
    public static function suite() {
        $suite = new CakeTestSuite('Lib tests');
        $suite->addTestDirectoryRecursive(join(DS, array(EPS_BANK_TRANSFER_APP,'Lib','EPS','tests')));
        return $suite;
    }
}