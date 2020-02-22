<?php
namespace EpsBankTransfer\Test\TestCase\Controller;

use Cake\TestSuite\TestCase;
use Cake\TestSuite\IntegrationTestTrait;
use EpsBankTransfer\Test\TestApp\Application;

use EpsBankTransfer\PaymentNotificationsController;

class PaymentNotificationsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    
    public function setUp()
    {
        $this->disableErrorHandlerMiddleware();
        $this->component =  $this->getMockBuilder('EpsBankTransfer\EpsComponent')
            ->setMethods(['HandleConfirmationUrl'])
            ->disableOriginalConstructor()
            ->getMock();
    }
        
    public function controllerSpy($event, $controller = null)
    {
        /* @var $controller PaymentNotificationsController */
        $controller = $event->getSubject();
        $controller->Eps = $this->component;            
    }

    public function testProcessCallsComponent()
    {        
        $this->component->expects($this->once())
                ->method('HandleConfirmationUrl')
                ->with('foo');
        $this->get('/eps_bank_transfer/process/foo');
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

        $this->get('/eps_bank_transfer/process/foo', ['return' => 'contents']);
        $this->assertResponseEquals('hello world');
    }    
}
