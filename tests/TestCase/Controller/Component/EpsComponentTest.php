<?php

namespace EpsBankTransfer\Test\TestCase\Controller\Component;

use at\externet\eps_bank_transfer;
use Cake\Cache\Cache;
use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventList;
use Cake\TestSuite\TestCase;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use EpsBankTransfer\Controller\Component\EpsComponent;
use EpsBankTransfer\Plugin;
use PHPUnit\Framework\MockObject\MockObject;

class EpsComponentTest extends TestCase
{

    /** @var EpsComponent eps component */
    public $Eps = null;

    /** @var MockObject|Controller */
    public $Controller = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->loadPlugins([\EpsBankTransfer\Plugin::class => ['routes' => true]]);

        date_default_timezone_set("UTC");

        $request = new ServerRequest();
        $response = new Response();
        /** @var MockObject|Controller */
        $this->Controller = $this->getMockBuilder('\Cake\Controller\Controller')
            ->setConstructorArgs([$request, $response])
            ->onlyMethods(['redirect'])
            ->getMock();

        /** @var EventManager */
        $eventmanager = $this->Controller->getEventManager();
        $eventmanager->setEventList(new EventList());

        $registry = new ComponentRegistry($this->Controller);
        $this->Eps = new EpsComponent($registry);

        $event = new Event('Controller.startup', $this->Controller);
        $this->Eps->startup($event);

        $published = new \hakito\Publisher\StaticPublished(Plugin::class);
        $published->SoCommunicator['live'] = $this->getMockBuilder('at\externet\eps_bank_transfer\SoCommunicator')
            ->getMock();
        Plugin::$EnableLogging = false;
        Cache::clear();
    }

    public function testGetBanksArray()
    {
        $expected = 'foo';
        /** @var MockObject */
        $communicator = Plugin::GetSoCommunicator();
        $communicator->expects($this->once())
                ->method('TryGetBanksArray')
                ->willReturn($expected);
        $actual = $this->Eps->GetBanksArray();
        $this->assertEquals($expected, $actual);
    }

    public function testGetBanksArrayCached()
    {
        $expected = 'Foo';
        Cache::write(Plugin::$CacheKeyPrefix . 'BanksArrayLive', $expected);
        $actual = $this->Eps->GetBanksArray();
        $this->assertEquals($expected, $actual);
    }

    public function testGetBanksArrayInvalidateCache()
    {
        $expected = 'Foo';
        /** @var MockObject */
        $communicator = Plugin::GetSoCommunicator();
        $communicator->expects($this->once())
                ->method('TryGetBanksArray')
                ->willReturn($expected);
        Cache::write(Plugin::$CacheKeyPrefix . 'BanksArray', 'Bar');
        $actual = $this->Eps->GetBanksArray(true);
        $this->assertEquals($actual, $expected);
    }

    public function testGetBanksArrayEmptyResultNotCached()
    {
        $expected = 'Foo';
        /** @var MockObject */
        $communicator = Plugin::GetSoCommunicator();
        $communicator->expects($this->exactly(2))
                ->method('TryGetBanksArray')
                ->willReturnOnConsecutiveCalls(null, $expected);
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
        /** @var MockObject */
        $communicator = Plugin::GetSoCommunicator();
        $communicator->expects($this->once())
                ->method('HandleConfirmationUrl')
                ->with($this->isType('callable'), $this->isType('callable'), 'foo', 'bar');
        $this->Eps->HandleConfirmationUrl('remi', 'foo', 'bar');
    }

    public function testHandleConfirmationUrlFiresConfirmationEvent()
    {
        $remittanceIdentifier = 'remi';
        $eRemittanceIdentifier = Plugin::Base64Encode(
            \Cake\Utility\Security::encrypt($remittanceIdentifier, 'A_SECRET_KEY_MUST_BE_32_BYTES_LONG'));
        $bankConfirmationDetails = new eps_bank_transfer\BankConfirmationDetails(
            new \SimpleXMLElement(eps_bank_transfer\BaseTest::GetEpsData('BankConfirmationDetailsWithoutSignature.xml')));
        $bankConfirmationDetails->SetRemittanceIdentifier($remittanceIdentifier);

        $mockSoCommunicatorBehavior = function( $wrapperCallback ) use ($bankConfirmationDetails) {
            $wrapperCallback('raw', $bankConfirmationDetails);
        };

        /** @var MockObject */
        $communicator = Plugin::GetSoCommunicator();
        $communicator->expects($this->once())
            ->method('HandleConfirmationUrl')
            ->willReturnCallback($mockSoCommunicatorBehavior);

        $this->Eps->HandleConfirmationUrl($eRemittanceIdentifier, 'raw', 'bar');

        $this->assertEventFiredWith('EpsBankTransfer.Confirmation',
        'args',
        [
            'raw' => 'raw',
            'bankConfirmationDetails' => $bankConfirmationDetails
        ], $this->Controller->getEventManager());
    }

    public function testHandleConfirmationUrlChecksRemittanceIdentifier()
    {
        $remittanceIdentifier = 'invalid_remittance_identifier';
        $eRemittanceIdentifier = Plugin::Base64Encode(
            \Cake\Utility\Security::encrypt($remittanceIdentifier, Configure::read('EpsBankTransfer.encryptionKey')));
        $bankConfirmationDetails = new eps_bank_transfer\BankConfirmationDetails(
            new \SimpleXMLElement(eps_bank_transfer\BaseTest::GetEpsData('BankConfirmationDetailsWithoutSignature.xml')));

        $mockSoCommunicatorBehavior = function( $wrapperCallback ) use ($bankConfirmationDetails) {
            $wrapperCallback('raw', $bankConfirmationDetails);
        };

        /** @var MockObject */
        $communicator = Plugin::GetSoCommunicator();
        $communicator->expects($this->once())
                ->method('HandleConfirmationUrl')
                ->will($this->returnCallback($mockSoCommunicatorBehavior));

        $this->expectException(eps_bank_transfer\UnknownRemittanceIdentifierException::class);
        $this->Eps->HandleConfirmationUrl($eRemittanceIdentifier, 'raw', 'bar');
    }

    public function testHandleConfirmationUrlCallsFiresVitalityCheckEvent()
    {
        $mockSoCommunicatorBehavior = function( $confirmCallback, $vitalityCallback ) {
            $vitalityCallback('raw', 'dummy vitality check details');
        };

        /** @var MockObject */
        $communicator = Plugin::GetSoCommunicator();
        $communicator->expects($this->once())
                ->method('HandleConfirmationUrl')
                ->with($this->isType('callable'), $this->isType('callable'), 'foo', 'bar')
                ->willReturnCallback($mockSoCommunicatorBehavior);
        $this->Eps->HandleConfirmationUrl('remi', 'foo', 'bar');

        $this->assertEventFiredWith('EpsBankTransfer.VitalityCheck',
        'args',
        [
            'raw' => 'raw',
            'vitalityCheckDetails' => 'dummy vitality check details'
        ], $this->Controller->getEventManager());
    }

    public function testPaymentRedirectErrorResponse()
    {
        $this->Eps->AddArticle('Foo', 3, "7");
        /** @var MockObject */
        $communicator = Plugin::GetSoCommunicator();
        $communicator->expects($this->once())
                ->method('SendTransferInitiatorDetails')
                ->willReturn(eps_bank_transfer\BaseTest::GetEpsData('BankResponseDetails004.xml'));
        $exception = null;
        try {
            $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null);
        } catch (\EpsBankTransfer\Exceptions\EpsAnswerException $th) {
            $exception = $th;
        }
        $expected = array('ErrorCode' => '004', 'ErrorMsg' => 'merchant not found!');
        $this->assertEquals($expected, $exception->ToArray());
    }

    public function testPaymentRedirectSuccess()
    {
        /** @var MockObject */
        $communicator = Plugin::GetSoCommunicator();
        $communicator->expects($this->once())
                ->method('SendTransferInitiatorDetails')
                ->willReturn(eps_bank_transfer\BaseTest::GetEpsData('BankResponseDetails000.xml'));

        $this->Controller->expects($this->once())
                ->method('redirect')
                ->with('http://epsbank.at/asdk3935jdlf043');

        $this->Eps->AddArticle('Foo', 3, "7");

        $actual = $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null);
        $this->assertNull($actual);
    }

    public function testPaymentRedirectErrorInvalidNumberOfMinutes()
    {
        $this->expectException('InvalidArgumentException', 'Expiration minutes value of "3" is not between 5 and 60.');

        $actual = $this->Eps->PaymentRedirect('1234567890ABCDEFG', null, null, null, 3);
        $this->assertNull($actual);
    }
}
