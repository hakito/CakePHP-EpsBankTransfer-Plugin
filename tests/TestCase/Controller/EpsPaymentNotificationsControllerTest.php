<?php

namespace Test\Case\Controller;

App::import('EpsBankTransfer.Test', 'Config');
App::import('EpsBankTransfer.Test', 'Helper');


class EpsPaymentNotificationsControllerTest extends ControllerTestCase
{
    /** @var EpsPaymentNotificationsController controller */
    public $Controller = null;
    
    public function setUp()
    {
        $this->Controller = $this->generate('EpsBankTransfer.EpsPaymentNotifications',
                array(
                    'methods' => array ('afterEpsBankTransferNotification'),
                    'components' => array('EpsBankTransfer.Eps')
                ));
    }

    public function testProcessCallsComponent()
    {
        $target = $this->Controller->Eps;
        $target->expects($this->once())
                ->method('HandleConfirmationUrl')
                ->with('foo');
        $this->testAction('/eps_bank_transfer/process/foo');
    }

    public function testProcessRenderedView()
    {
        $target = $this->Controller->Eps;
        /** @noinspection PhpUnusedParameterInspection */
        $target->expects($this->once())
                ->method('HandleConfirmationUrl')
                ->will($this->returnCallback(function($eRemittanceIdentifier, $rawPostStream = 'php://input', $outputStream = 'php://output')
        {
            $fh = fopen($outputStream, 'w+');
            fwrite($fh, 'hello world');
            fclose($fh);
        }));

        $this->testAction('/eps_bank_transfer/process/foo', array('return' => 'contents'));
        $this->assertEquals('hello world', $this->contents);
    }
}
