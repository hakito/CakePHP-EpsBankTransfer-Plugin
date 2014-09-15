<?php

App::uses('EpsPaymentNotificationsController', 'EpsBankTransfer.Controller');
App::uses('EpsCommon', 'EpsBankTransfer.Lib');
App::import('EpsBankTransfer.Test', 'Config');
App::import('EpsBankTransfer.Test', 'Helper');

use at\externet\eps_bank_transfer;

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
                ->method('HandleConfirmationUrl');
        $this->testAction('/eps_bank_transfer/process/foo');
    }     
}
