<?php

App::uses('EpsBankTransferAppController', 'EpsBankTransfer.Controller');

class EpsPaymentNotificationsController extends EpsBankTransferAppController
{

    public $components = array('EpsBankTransfer.Eps');
    
    public function process()
    {   
        $this->Eps->HandleConfirmationUrl();
    }

}
