<?php

App::uses('EpsBankTransferAppController', 'EpsBankTransfer.Controller');

/**
 * @property EpsPaymentNotification $InstantPaymentNotification Model
 */
class EpsPaymentNotificationsController extends EpsBankTransferAppController
{

    public $components = array('Eps');
    
    public function process()
    {
        print_r($this->data);
        /*
        $result = null;
        try
        {
            $this->InstantPaymentNotification->getEventManager()->attach(array($this, '__processTransaction'), 'PaypalIpn.afterProcess');
            $result = $this->InstantPaymentNotification->process($id);
        }
        catch (PaypalIpnEmptyRawDataExpection $e)
        {
            $result = 'empty';
        }
        $this->_stop($result);
         * 
         */
    }

}
