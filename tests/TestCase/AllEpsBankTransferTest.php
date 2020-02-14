<?php
/**
 * All  plugin tests
 */
namespace Test\Case;

class AllEpsBankTransferTest extends TestCase {

	/**
	 * Suite define the tests for this plugin
	 *
	 * @return CakeTestSuite
	 */
	public static function suite() {
		$suite = new CakeTestSuite('All test');

		$path = Plugin::path('EpsBankTransfer') . 'Test' . DS . 'Case' . DS;
		$suite->addTestDirectoryRecursive($path);

		return $suite;
	}

}
