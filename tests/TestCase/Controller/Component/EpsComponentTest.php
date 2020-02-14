<?php

namespace Test\Case\Controller\Component;

App::import('EpsBankTransfer.Test', 'Config');


class EpsComponentTest extends TestCase
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

        date_default_timezone_set("UTC");
        $Collection = new ComponentRegistry();
        $mockedController = $this->getMock('Controller', array('afterEpsBankTransferNotification'));
        $this->Controller = $mockedController;
        $this->Eps = new EpsComponent($Collection);
        /** @noinspection PhpParamsInspection */
        $this->Eps->startup($mockedController);
        EpsCommon::$SoCommunicator = $this->getMock('at\externet\eps_bank_transfer\SoCommunicator');
        EpsCommon::$EnableLogging = false;
        Cache::clear();
    }

    public function testGetBanksArray()
    {
        $expected = 'foo';
        EpsCommon::GetSoCommunicator()->expects($this->once())
                ->method('TryGetBanksArray')
                ->will($this->returnValue($expected));
        $actual = $this->Eps->GetBanksArray();
        $this->assertEquals($expected, $actual);
    }

    public function testGetBanksArrayCached()
    {
        $expected = 'Foo';
        Cache::write(EpsCommon::$CacheKeyPrefix . 'BanksArray', $expected);
        $actual = $this->Eps->GetBanksArray();
        $this->assertEquals($expected, $actual);
    }

    public function testGetBanksArrayInvalidateCache()
    {
        $expected = 'Foo';
        EpsCommon::GetSoCommunicator()->expects($this->once())
                ->method('TryGetBanksArray')
                ->will($this->returnValue($expected));
        Cache::write(EpsCommon::$CacheKeyPrefix . 'BanksArray', 'Bar');
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
        $this->assertEquals($expected, $actual);
    }

    public function testAddArticleAddsToArray()
    {
        $this->Eps->AddArticle('Name', 3, 45);
        $this->assertFalse(empty($this->Eps->Articles));
    }

    public function testAddArticleAddWithIdentifier()
    {
        $this->Eps->AddArticle('Name', 3, 45, 'myarticle');
        $this->assertTrue(isset($this->Eps->Articles['myarticle']));
    }

    public function testAddArticleAddContent()
    {
        $this->Eps->AddArticle('Foo', 3, 5);
        /** @var eps_bank_transfer\WebshopArticle */
        $article = $this->Eps->Articles[0];
        $this->assertSame("Foo", $article->Name);
        $this->assertSame(3, $article->Count);
        $this->assertSame("0.05", $article->Price);
    }

    public function testAddArticleIncreasesTotal()
    {
        $this->Eps->AddArticle('Foo', 3, "7");
        $this->assertSame(21, $this->Eps->Total);
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
        $this->assertEquals($expected, $actual);
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

        /** @noinspection PhpParamsInspection */
        $this->Eps->startUp($controller);
        $this->Eps->AddArticle('Foo', 3, "7");

        $actual = $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null);
        $this->assertNull($actual);
    }

    public function testPaymentRedirectErrorInvalidNumberOfMinutes()
    {
        $controller = $this->getMock('Controller', array('redirect'));

        /** @noinspection PhpParamsInspection */
        $this->Eps->startUp($controller);
        $this->setExpectedException('InvalidArgumentException', 'Expiration minutes value of "3" is not between 5 and 60.');

        $actual = $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null, null, 3);
        $this->assertNull($actual);
    }
}
