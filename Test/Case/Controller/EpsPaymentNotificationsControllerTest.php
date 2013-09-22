<?php

App::uses('EpsPaymentNotificationsController', 'EpsBankTransfer.Controller');
require_once EPS_BANK_TRANSFER_APP . 'Test' . DS . 'Helper.php';

class EpsPaymentNotificationsControllerTest extends ControllerTestCase
{
    public $Controller = null;
    
    public function setUp()
    {
        $this->Controller = new EpsPaymentNotificationsController();
    }
    
    public function testProcess()
    {
        $data = null;
        $this->testAction('/eps_bank_transfer/process', $data);
        //$this->Controller->process();
    }
}