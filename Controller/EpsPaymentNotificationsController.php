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
        // TODO 7.1.8. Schritt III-2: Vitality Check eps SO-Händler
        // TODO 7.1.9. Schritt III-3: Bestätigung Vitality Check Händler-eps SO
        
        $bankConfirmationDetails = $this->Eps->GetBankConfirmationDetailsArray();
        $paymentConfirmationDetails = &$bankConfirmationDetails['PaymentConfirmationDetails'];  
        if (!method_exists($this, 'afterEpsBankTransferNotification'))
                throw new InternalErrorException('Missing afterEpsBankTransferNotification() implementation');
        
        $ret = $this->afterEpsBankTransferNotification($paymentConfirmationDetails['PaymentReferenceIdentifier'], $paymentConfirmationDetails['StatusCode'], $bankConfirmationDetails);
        
        if ($ret !== true)
            throw new InternalErrorException('afterEpsBankTransferNotification() did not return TRUE');
        
        // TOOD Schritt III-8: Bestätigung Erhalt eps Zahlungsbestätigung Händler-eps SO
        
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
