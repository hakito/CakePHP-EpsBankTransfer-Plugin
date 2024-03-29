<?php
namespace EpsBankTransfer\Test\TestCase\Controller;

use Cake\TestSuite\TestCase;
use Cake\TestSuite\IntegrationTestTrait;
use EpsBankTransfer\Controller\Component\EpsComponent;

class PaymentNotificationsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /** @var MockObject|EpsComponent */
    public $component;

    public function setUp(): void
    {
        parent::setUp();
        $this->disableErrorHandlerMiddleware();
        $this->component =  $this->getMockBuilder(EpsComponent::class)
            ->onlyMethods(['HandleConfirmationUrl'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function controllerSpy($event, $controller = null)
    {
        /** @var $controller PaymentNotificationsController */
        $controller = $event->getSubject();
        $controller->Eps = $this->component;
    }

    public function testProcessCallsComponent()
    {
        $this->component->expects($this->once())
                ->method('HandleConfirmationUrl')
                ->with('foo');
        $this->post('/eps_bank_transfer/process/foo');
    }

    public function testProcessRenderedView()
    {
        $this->component->expects($this->once())
                ->method('HandleConfirmationUrl')
                ->will($this->returnCallback(function($eRemittanceIdentifier, $rawPostStream = 'php://input', $outputStream = 'php://output')
        {
            $fh = fopen($outputStream, 'w+');
            fwrite($fh, 'hello world');
            fclose($fh);
        }));

        $this->post('/eps_bank_transfer/process/foo', ['return' => 'contents']);
        $this->assertResponseEquals('hello world');
    }
}
