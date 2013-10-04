<?php

App::uses('EpsComponent', 'EpsBankTransfer.Controller/Component');
App::uses('ComponentCollection', 'Controller');
App::uses('HttpResponse', 'Network/Http');

use at\externet\eps_bank_transfer;

require_once EPS_BANK_TRANSFER_APP . 'Test' . DS . 'Helper.php';

class EpsComponentTest extends CakeTestCase
{

    /** @var \EpsComponent eps component */
    public $Eps = null;

    public $Controller = null;
    
    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $Collection = new ComponentCollection();
        $mockedController = $this->getMock('Controller', array('afterEpsBankTransferNotification'));
        $this->Controller = $mockedController;
        $this->Eps = new EpsComponent($Collection);
        $this->Eps->startup($mockedController);
        $this->Eps->SoCommunicator = $this->getMock('at\externet\eps_bank_transfer\SoCommunicator');
        Cache::clear();
    }

    public function testGetBanksArray()
    {
        $expected = 'foo';
        $this->Eps->SoCommunicator->expects($this->once())
                ->method('TryGetBanksArray')
                ->will($this->returnValue($expected));
        $actual = $this->Eps->GetBanksArray();
        $this->assertEqual($expected, $actual);
    }

    public function testGetBanksArrayCached()
    {
        $expected = 'Foo';
        Cache::write($this->Eps->CacheKeyPrefix . 'BanksArray', $expected);
        $actual = $this->Eps->GetBanksArray();
        $this->assertEqual($actual, $expected);
    }

    public function testGetBanksArrayInvalidateCache()
    {
        $expected = 'Foo';
        $this->Eps->SoCommunicator->expects($this->once())
                ->method('TryGetBanksArray')
                ->will($this->returnValue($expected));
        Cache::write($this->Eps->CacheKeyPrefix . 'BanksArray', 'Bar');
        $actual = $this->Eps->GetBanksArray(true);
        $this->assertEquals($actual, $expected);
    }

    public function testGetBanksArrayEmptyResultNotCached()
    {
        $expected = 'Foo';
        $this->Eps->SoCommunicator->expects($this->at(0))
                ->method('TryGetBanksArray')
                ->will($this->returnValue(null));
        $this->Eps->SoCommunicator->expects($this->at(1))
                ->method('TryGetBanksArray')
                ->will($this->returnValue($expected));
        $this->Eps->GetBanksArray();
        $actual = $this->Eps->GetBanksArray();
        $this->assertEqual($actual, $expected);
    }

    public function testAddArticleAddsToArray()
    {
        $this->Eps->AddArticle('Name', 3, 45);
        $this->assertIdentical(empty($this->Eps->Articles), false);
    }

    public function testAddArticleAddWithIdentifier()
    {
        $this->Eps->AddArticle('Name', 3, 45, 'myarticle');
        $this->assertEqual(isset($this->Eps->Articles['myarticle']), true);
    }

    public function testAddArticleAddContent()
    {
        $this->Eps->AddArticle('Foo', 3, 5);
        /** @var eps_bank_transfer\WebshopArticle */
        $article = $this->Eps->Articles[0];
        $this->assertIdentical($article->Name, "Foo");
        $this->assertIdentical($article->Count, 3);
        $this->assertIdentical($article->Price, "0.05");
    }

    public function testAddArticleIncreasesTotal()
    {
        $this->Eps->AddArticle('Foo', 3, "7");
        $this->assertIdentical($this->Eps->Total, 21);
    }

    public function testHandleConfirmationUrlCallsSoCommunicator()
    {
        $this->Eps->SoCommunicator->expects($this->once())
                ->method('HandleConfirmationurl')
                ->with($this->equalTo(array($this->Controller, 'afterEpsBankTransferNotification')), null, 'foo', 'bar');
        $this->Eps->HandleConfirmationUrl('foo', 'bar');
    }

    public function testHandleConfirmationUrlCallsSoCommunicatorWithVitalityCheckCallback()
    {
        $config = Configure::read('EpsBankTransfer');
        $config['VitalityCheckCallback'] = 'MyVitalityCheckCallback';
        Configure::write('EpsBankTransfer', $config);
        $this->Eps->VitalityCheckCallback = 'MyVitalityCheckCallback';
        $this->Eps->SoCommunicator->expects($this->once())
                ->method('HandleConfirmationurl')
                ->with($this->anything(), $this->equalTo(array($this->Controller, 'MyVitalityCheckCallback')), 'foo', 'bar');
        $this->Eps->HandleConfirmationUrl('foo', 'bar');
    }
    
    public function testPaymentRedirectErrorResponse()
    {
        $this->Eps->AddArticle('Foo', 3, "7");
        $this->Eps->SoCommunicator->expects($this->once())
                ->method('SendTransferInitiatorDetails')
                ->will($this->returnValue(eps_bank_transfer\BaseTest::GetEpsData('BankResponseDetails004.xml')));
        $actual = $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null);
        $expected = array('ErrorCode' => '004', 'ErrorMsg' => 'merchant not found!');
        $this->assertEqual($actual, $expected);
    }

    public function testPaymentRedirectSuccess()
    {
        $controller = $this->getMock('Controller', array('redirect'));

        $this->Eps->SoCommunicator->expects($this->once())
                ->method('SendTransferInitiatorDetails')
                ->will($this->returnValue(eps_bank_transfer\BaseTest::GetEpsData('BankResponseDetails000.xml')));

        $controller->expects($this->once())
                ->method('redirect')
                ->with('http://epsbank.at/asdk3935jdlf043');

        $this->Eps->startUp($controller);
        $this->Eps->AddArticle('Foo', 3, "7");

        $actual = $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null);
        $this->assertEqual($actual, null);
    }
}