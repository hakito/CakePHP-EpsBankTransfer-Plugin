<?php

App::uses('EpsComponent', 'EpsBankTransfer.Controller/Component');
App::uses('ComponentCollection', 'Controller');
App::uses('HttpResponse', 'Network/Http');
App::uses('EpsCommon', 'EpsBankTransfer.Lib');
App::import('EpsBankTransfer.Test', 'Config');

use at\externet\eps_bank_transfer;

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
        EpsCommon::$SoCommunicator = $this->getMock('at\externet\eps_bank_transfer\SoCommunicator');
        Cache::clear();
    }

    public function testGetBanksArray()
    {
        $expected = 'foo';
        EpsCommon::GetSoCommunicator()->expects($this->once())
                ->method('TryGetBanksArray')
                ->will($this->returnValue($expected));
        $actual = $this->Eps->GetBanksArray();
        $this->assertEqual($expected, $actual);
    }

    public function testGetBanksArrayCached()
    {
        $expected = 'Foo';
        Cache::write(EpsCommon::$CacheKeyPrefix . 'BanksArray', $expected);
        $actual = $this->Eps->GetBanksArray();
        $this->assertEqual($actual, $expected);
    }

    public function testGetBanksArrayInvalidateCache()
    {
        $expected = 'Foo';
        EpsCommon::GetSoCommunicator()->expects($this->once())
                ->method('TryGetBanksArray')
                ->will($this->returnValue($expected));
        Cache::write($this->Eps->CacheKeyPrefix . 'BanksArray', 'Bar');
        $actual = $this->Eps->GetBanksArray(true);
        $this->assertEquals($actual, $expected);
    }

    public function testGetBanksArrayEmptyResultNotCached()
    {
        $expected = 'Foo';
        EpsCommon::GetSoCommunicator()->expects($this->at(0))
                ->method('TryGetBanksArray')
                ->will($this->returnValue(null));
        EpsCommon::GetSoCommunicator()->expects($this->at(1))
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
        EpsCommon::GetSoCommunicator()->expects($this->once())
                ->method('HandleConfirmationurl')
                ->with($this->isType('callable'), null, 'foo', 'bar');
        $this->Eps->HandleConfirmationUrl('remi', 'foo', 'bar');
    }

    public function testHandleConfirmationUrlCallsSoCommunicatorWithVitalityCheckCallback()
    {
        $config = Configure::read('EpsBankTransfer');
        $config['VitalityCheckCallback'] = 'MyVitalityCheckCallback';
        Configure::write('EpsBankTransfer', $config);
        $this->Eps->VitalityCheckCallback = 'MyVitalityCheckCallback';
        EpsCommon::GetSoCommunicator()->expects($this->once())
                ->method('HandleConfirmationurl')
                ->with($this->anything(), $this->equalTo(array($this->Controller, 'MyVitalityCheckCallback')), 'foo', 'bar');
        $this->Eps->HandleConfirmationUrl('remi', 'foo', 'bar');
    }
    
    public function testPaymentRedirectErrorResponse()
    {
        $this->Eps->AddArticle('Foo', 3, "7");
        EpsCommon::GetSoCommunicator()->expects($this->once())
                ->method('SendTransferInitiatorDetails')
                ->will($this->returnValue(eps_bank_transfer\BaseTest::GetEpsData('BankResponseDetails004.xml')));
        $actual = $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null);
        $expected = array('ErrorCode' => '004', 'ErrorMsg' => 'merchant not found!');
        $this->assertEqual($actual, $expected);
    }

    public function testPaymentRedirectSuccess()
    {
        $controller = $this->getMock('Controller', array('redirect'));

        EpsCommon::GetSoCommunicator()->expects($this->once())
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
