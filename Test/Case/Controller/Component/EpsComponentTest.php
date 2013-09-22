<?php

App::uses('EpsComponent', 'EpsBankTransfer.Controller/Component');
App::uses('ComponentCollection', 'Controller');
App::uses('HttpResponse', 'Network/Http');

use at\externet\eps_bank_transfer;
require_once EPS_BANK_TRANSFER_APP . 'Test' . DS . 'Helper.php';

class EpsComponentTest extends CakeTestCase
{

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
        $this->Eps->HttpSocket = $this->getMock('HttpSocket');//new MockHttpSocket();
        Cache::clear();
    }
    
    public function testGetBanksArray()
    {
        $this->httpGetReturns($this->once(), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListSample.xml');
        $banks = $this->Eps->GetBanksArray();
        $this->assertEqual($banks['Testbank']['bic'], "TESTBANKXXX");
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
        $this->httpGetReturns($this->once(), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListSample.xml');
        Cache::write($this->Eps->CacheKeyPrefix . 'BanksArray', $expected);
        $actual = $this->Eps->GetBanksArray(true);
        $this->assertNotEqual($actual, $expected);
    }
    
    public function testGetBanklistBic()
    {
        $this->httpGetReturns($this->once(), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListSample.xml');
        $banks = $this->Eps->GetBanksXml();
        $this->assertEqual($banks->bank[0]->bic, "TESTBANKXXX");
    }

    public function testGetBankListTwice()
    {
        $this->httpGetReturns($this->once(), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListSample.xml');
        $this->Eps->GetBanksXml();
        $banks = $this->Eps->GetBanksXml();
        $this->assertEqual($banks->bank[0]->bic, "TESTBANKXXX");
    }

    public function testGetBanklistReadError()
    {
        $this->expectException('CakeException', 'Could not load document. Server returned code: 404');
        $this->httpGetReturns($this->once(), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListSample.xml', 404);
        $this->Eps->GetBanksXml();
    }
   
    public function testGetInvalidXmlResponseError()
    {
        $this->expectException('CakeException', "XML does not validate against XSD. Element '{http://www.eps.or.at/epsSO/epsSOBankListProtocol/201008}book': This element is not expected. Expected is ( {http://www.eps.or.at/epsSO/epsSOBankListProtocol/201008}bic");
        $this->httpGetReturns($this->once(), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListInvalid.xml');
        $this->Eps->GetBanksXml();
    }

    public function testInvalidResponseIsUncached()
    {
        $this->httpGetReturns($this->exactly(2), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListSample.xml', 404);
        $exceptionThrown = false;
        try
        {
            $this->Eps->GetBanksXml();
        }
        catch (CakeException $e)
        {
            $exceptionThrown = true;
        }
        
        $this->assertEqual($exceptionThrown, true);
        
        $banks = null;
        $exceptionThrown = false;
        try
        {
            $banks = $this->Eps->GetBanksXml();
        }
        catch (CakeException $e)
        {
            $exceptionThrown = true;
        }
        
        $this->assertEqual($exceptionThrown, true);
        $this->assertEqual($banks, null);
    }

    public function testInvalidXmlResponseIsUncached()
    {
        $this->httpGetReturns($this->at(0), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListInvalid.xml');
        $this->httpGetReturns($this->at(1), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListSample.xml');
        $exceptionThrown = false;
        try
        {
            $this->Eps->GetBanksXml();
        }
        catch (CakeException $e)
        {
            $exceptionThrown = true;
        }
        $this->assertEqual($exceptionThrown, true);
        
        $banks = $this->Eps->GetBanksXml();
        $this->assertEqual($banks->bank[0]->bic, "TESTBANKXXX");
    }

    public function testGetBanksArrayOrNullReturnsBanks()
    {
        $this->httpGetReturns($this->at(0), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListSample.xml');
        $banks = $this->Eps->GetBanksArrayOrNull();
        $this->assertEqual($banks['Testbank']['bic'], "TESTBANKXXX");
    }

    public function testGetBanksArrayOrNullReturnsNull()
    {
        $this->httpGetReturns($this->at(0), 'https://routing.eps.or.at/appl/epsSO/data/haendler/v2_4', 'BankListInvalid.xml');
        $banks = $this->Eps->GetBanksArrayOrNull();
        $this->assertEqual($banks, null);
    }

    public function testGetValidatedSimpleXmlElment()
    {
        $banks = EpsComponent::GetValidatedSimpleXmlElement(eps_bank_transfer\GetEpsData("BankListSample.xml"), 'epsSOBankListProtocol.xsd');
        $this->assertEqual($banks->bank[0]->bic, "TESTBANKXXX");
    }

    public function testGetInvalidSimpleXmlElementThrowsException()
    {
        $this->expectException('CakeException', "XML does not validate against XSD. Element '{http://www.eps.or.at/epsSO/epsSOBankListProtocol/201008}book': This element is not expected. Expected is ( {http://www.eps.or.at/epsSO/epsSOBankListProtocol/201008}bic");
        EpsComponent::GetValidatedSimpleXmlElement(eps_bank_transfer\GetEpsData("BankListInvalid.xml"), 'epsSOBankListProtocol.xsd');
    }

    public function testGenerateTransferIinitiatorDetails()
    {
        $eSimpleXml = EpsComponent::GetValidatedSimpleXmlElement(eps_bank_transfer\GetEpsData('TransferInitiatorDetailsWithoutSignature.xml'), 'EPSProtocol-V24.xsd');

        $webshopArticle = new eps_bank_transfer\WebshopArticle("Toaster", 1, 15000);
        $transferMsgDetails = new eps_bank_transfer\TransferMsgDetails("http://10.18.70.8:7001/vendorconfirmation", "http://10.18.70.8:7001/transactionok?danke.asp", "http://10.18.70.8:7001/transactionnok?fehler.asp");
        $transferMsgDetails->TargetWindowNok = $transferMsgDetails->TargetWindowOk = 'Mustershop';
        
        $data = new eps_bank_transfer\TransferInitiatorDetails('AKLJS231534', 'topSecret', 'GAWIATW1XXX', 'Max Mustermann', 'AT611904300234573201', '1234567890ABCDEFG', 'AT1234567890XYZ', 15000, $webshopArticle, $transferMsgDetails, '2007-03-16');
        $aSimpleXml = $data->GetSimpleXml();
        
        EpsComponent::GetValidatedSimpleXmlElement($aSimpleXml->asXML(), 'EPSProtocol-V24.xsd');
        
        $eDom = new DOMDocument();
        $eDom->loadXML($eSimpleXml->asXML());
        $eDom->formatOutput = true;
        $eDom->preserveWhiteSpace = false;
        $eDom->normalizeDocument();
        
        $this->assertEqual($aSimpleXml->asXML(), $eDom->saveXML());
    }
    
    public function testPaymentOrderCallsHttpSocketPost()
    {
        $transferInitiatorDetails = $this->getMockedTransferInitiatorDetails();        
        $this->httpPostReturns($this->at(0), 'https://routing.eps.or.at/appl/epsSO/transinit/eps/v2_4', $this->stringContains('xml'), 'BankResponseDetails004.xml');
        $actual = $this->Eps->SendPaymentOrder($transferInitiatorDetails); 
        $this->assertEqual($actual->asXml(), eps_bank_transfer\GetEpsData('BankResponseDetails004.xml'));
    }
    
    public function testPaymentOrderThrowsExceptionOn404()
    {
        $transferInitiatorDetails = $this->getMockedTransferInitiatorDetails();        
        $this->httpPostReturns($this->at(0), 'https://routing.eps.or.at/appl/epsSO/transinit/eps/v2_4', $this->stringContains('xml'), 'BankResponseDetails004.xml', 404);
        $this->expectException('CakeException', "Could not load document. Server returned code: 404");
        $this->Eps->SendPaymentOrder($transferInitiatorDetails);
    }
    
    public function testPaymentOrderWithPreselectedBank()
    {
        $url = 'https://routing.eps.or.at/appl/epsSO/transinit/eps/v2_4/23ea3d14-278c-4e81-a021-d7b77492b611';
        $transferInitiatorDetails = $this->getMockedTransferInitiatorDetails();        
        $this->httpPostReturns($this->at(0), $url, $this->stringContains('xml'), 'BankResponseDetails000.xml');
        $this->Eps->SendPaymentOrder($transferInitiatorDetails, $url);
    }
    
    public function testPaymentOrderThrowsExceptionOnInvalidXmlResponse()
    {
        $url = 'https://routing.eps.or.at/appl/epsSO/transinit/eps/v2_4/23ea3d14-278c-4e81-a021-d7b77492b611';
        $transferInitiatorDetails = $this->getMockedTransferInitiatorDetails();        
        $this->httpPostReturns($this->at(0), $url, $this->stringContains('xml'), 'BankResponseDetailsInvalid.xml');
        $this->expectException('CakeException', "XML does not validate against XSD. Element '{http://www.stuzza.at/namespaces/eps/protocol/2011/11}ErrorCode': '12345' is not a valid value of the local atomic type.");
        $this->Eps->SendPaymentOrder($transferInitiatorDetails, $url);        
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
    
    // HELPER FUNCTIONS

    private function httpPostReturns($times, $url, $post, $responseFile, $code = 200)
    {
        $this->Eps->HttpSocket->expects($times)
                ->method('post')
                ->with($this->equalTo($url), $post)
                ->will($this->returnValue(EpsComponentTest::getHttpResponse($responseFile, $code)));
    }
    
    private function httpGetReturns($times, $url, $responseFile, $code = 200)
    {
        $this->Eps->HttpSocket->expects($times)
                ->method('get')
                ->with($this->equalTo($url))
                ->will($this->returnValue(EpsComponentTest::getHttpResponse($responseFile, $code)));
    }

    private static function getHttpResponse($responseFile, $code = 200)
    {
        $response = new HttpResponse();
        $response->body = eps_bank_transfer\GetEpsData($responseFile);
        $response->code = $code;
        return $response;
    }

    private function getMockedTransferInitiatorDetails()
    {
        $simpleXml = $this->getMock('at\externet\eps_bank_transfer\EpsXmlElement', null, array('<xml/>'));
        $simpleXml->expects($this->any())
                ->method('asXML')
                ->will($this->returnValue('<xml>Mocked Data'));
        
        $transferInitiatorDetails = $this->getMockBuilder('at\externet\eps_bank_transfer\TransferInitiatorDetails')->disableOriginalConstructor()->getMock();
        $transferInitiatorDetails->expects($this->any())
                ->method('GetSimpleXml')
                ->will($this->returnValue($simpleXml));
        return $transferInitiatorDetails;
    }
}