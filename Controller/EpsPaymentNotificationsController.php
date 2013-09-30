<?php

App::uses('EpsBankTransferAppController', 'EpsBankTransfer.Controller');

/**
 * @property EpsPaymentNotification $InstantPaymentNotification Model
 */
class EpsPaymentNotificationsController extends EpsBankTransferAppController
{

    public $components = array('EpsBankTransfer.Eps');
    
    public function process()
    {        
        $bankConfirmationDetails = $this->Eps->GetBankConfirmationDetailsArray();
        $paymentConfirmationDetails = &$bankConfirmationDetails['PaymentConfirmationDetails'];  
        if (!method_exists($this, 'afterEpsBankTransferNotification'))
                throw new InternalErrorException('Missing afterEpsBankTransferNotification() implementation');
        
        $ret = $this->afterEpsBankTransferNotification($paymentConfirmationDetails['PaymentReferenceIdentifier'], $paymentConfirmationDetails['StatusCode'], $bankConfirmationDetails);
        
        if ($ret !== true)
            throw new InternalErrorException('afterEpsBankTransferNotification() did not return TRUE');
        
        
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
