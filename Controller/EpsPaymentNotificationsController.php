<?php

App::uses('EpsBankTransferAppController', 'EpsBankTransfer.Controller');

/**
 * @property EpsComponent Eps
 */
class EpsPaymentNotificationsController extends EpsBankTransferAppController
{

    public $components = array('EpsBankTransfer.Eps');
    
    public function process($eRemittanceIdentifier)
    {
        ob_start();
        $this->Eps->HandleConfirmationUrl($eRemittanceIdentifier);
        $contents = ob_get_clean();
        $this->set('contents', $contents);
    }

}
