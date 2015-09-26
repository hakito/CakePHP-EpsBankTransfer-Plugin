<?php
/**
 * All  plugin tests
 */
class AllEpsBankTransferTest extends CakeTestCase {

	/**
	 * Suite define the tests for this plugin
	 *
	 * @return CakeTestSuite
	 */
	public static function suite() {
		$suite = new CakeTestSuite('All test');

		$path = CakePlugin::path('EpsBankTransfer') . 'Test' . DS . 'Case' . DS;
		$suite->addTestDirectoryRecursive($path);

		return $suite;
	}

}
