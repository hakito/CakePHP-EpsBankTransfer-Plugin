<?php

App::uses('EpsPaymentNotificationsController', 'EpsBankTransfer.Controller');
require_once EPS_BANK_TRANSFER_APP . 'Test' . DS . 'Helper.php';
use at\externet\eps_bank_transfer;

class EpsPaymentNotificationsControllerTest extends ControllerTestCase
{
    /** @var EpsPaymentNotificationsController controller */
    public $Controller = null;
    
    public function setUp()
    {
        $this->Controller = $this->generate('EpsBankTransfer.EpsPaymentNotifications',
                array(
                    'methods' => array ('afterEpsBankTransferNotification')
                ));
    }
    
    public function testProcessCallsCallbackWithRemittanceIdentifier()
    {
        $options = array('method' => 'POST');
        $this->Controller->Eps->RawPostStream = eps_bank_transfer\GetEpsDataPath('BankConfirmationDetailsWithoutSignature.xml');      
        $this->Controller->expects($this->once())
                ->method('afterEpsBankTransferNotification')
                ->with('120000302122320812201106461', 'OK', $this->anything())
                ->will($this->returnValue(true));
        
        $this->testAction('/eps_bank_transfer/process', $options);
    }
    
    public function testProcessThrowsExceptionOnMissingCallback()
    {
        $options = array('method' => 'POST');
        $this->Controller = $this->generate('EpsBankTransfer.EpsPaymentNotifications');
        $this->Controller->Eps->RawPostStream = eps_bank_transfer\GetEpsDataPath('BankConfirmationDetailsWithoutSignature.xml');      
        $this->expectException("InternalErrorException", 'Missing afterEpsBankTransferNotification() implementation');
        $this->testAction('/eps_bank_transfer/process', $options);
    }
    
    public function testProcessThrowsExceptionOnInvalidCallbackReturnValue()
    {
        $options = array('method' => 'POST');
        $this->Controller->Eps->RawPostStream = eps_bank_transfer\GetEpsDataPath('BankConfirmationDetailsWithoutSignature.xml');      
        $this->expectException("InternalErrorException", 'afterEpsBankTransferNotification() did not return TRUE');

        $this->Controller->expects($this->once())
                ->method('afterEpsBankTransferNotification');
        
        $this->testAction('/eps_bank_transfer/process', $options);        
    }
}