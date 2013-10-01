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

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $Collection = new ComponentCollection();
        $this->Eps = new EpsComponent($Collection);
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
                ->method('HandleConfirmationUrl');

        $this->Eps->HandleConfirmationUrl(function()
                {

                });
    }

    public function testHandleConfirmationUrlWrapsCallaback()
    {
        $this->Eps->SoCommunicator->expects($this->once())
                ->method('HandleConfirmationUrl')
                ->with($this->isType('callable'), 'php://input');
        $this->Eps->HandleConfirmationUrl(function()
                {

                });
    }

    public function testHandleConfirmationExceptionWhenNoCallable()
    {
        $this->expectException('\at\externet\eps_bank_transfer\InvalidCallbackException');
        $this->Eps->HandleConfirmationUrl('something');
    }

    public function testHandleConfirmationUrlExceptionOnError()
    {
        $filename = 'BankConfirmationDetailsWithoutSignature.xml';
        $dataPath = eps_bank_transfer\BaseTest::GetEpsDataPath($filename);
        $this->Eps->SoCommunicator = new eps_bank_transfer\SoCommunicator();

        $actual = array();
        $this->Eps->HandleConfirmationUrl(function($remittanceIdentifier, $statusCode, $rawResult) use (&$actual)
                {
                    $actual['id'] = $remittanceIdentifier;
                    $actual['code'] = $statusCode;
                    $actual['raw'] = $rawResult;
                    return true;
                }, $dataPath);

        $expected = array(
            'id' => 'AT1234567890XYZ',
            'code' => 'OK',
            'raw' => eps_bank_transfer\BaseTest::GetEpsData($filename)
        );

        $this->assertEqual($actual, $expected);
    }

    /*
     *

      public function testPaymentRedirectInvalidXml()
      {
      $this->Eps->AddArticle('Foo', 3, "7");
      $this->expectException('CakeException', "XML does not validate against XSD. Element '{http://www.stuzza.at/namespaces/eps/protocol/2011/11}ErrorCode': '12345' is not a valid value of the local atomic type.");
      $this->httpPostReturns($this->at(0), 'https://routing.eps.or.at/appl/epsSO/transinit/eps/v2_4', $this->stringContains('xml'), 'BankResponseDetailsInvalid.xml');
      $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null);
      }

      public function testPaymentRedirectErrorResponse()
      {
      $this->Eps->AddArticle('Foo', 3, "7");
      $this->httpPostReturns($this->at(0), 'https://routing.eps.or.at/appl/epsSO/transinit/eps/v2_4', $this->stringContains('xml'), 'BankResponseDetails004.xml');
      $actual = $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null);
      $expected = array('ErrorCode' => '004', 'ErrorMsg' => 'merchant not found!');
      $this->assertEqual($actual, $expected);
      }

      public function testPaymentRedirectSuccess()
      {
      $controller = $this->getMock('Controller', array('redirect'));
      $this->Eps->startUp($controller);
      $this->Eps->AddArticle('Foo', 3, "7");
      $this->httpPostReturns($this->at(0), 'https://routing.eps.or.at/appl/epsSO/transinit/eps/v2_4', $this->stringContains('xml'), 'BankResponseDetails000.xml');
      $actual = $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null);
      $this->assertEqual($actual, null);
      }

      public function testGetBankConfirmationDetailsArrayThrowsExceptionWhenNoDataReceived()
      {

      $this->assertEqual($this->Eps->RawPostStream, 'php://input');
      $this->expectException('BadRequestException', 'Could not read BankConfirmationDetails from input stream');
      $this->Eps->GetBankConfirmationDetailsArray();
      }

     */
}