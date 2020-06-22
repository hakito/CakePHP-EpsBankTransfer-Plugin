<?php

/**
 * @property EpsComponent Eps
 */
namespace EpsBankTransfer\Controller;

class PaymentNotificationsController extends AppController
{

    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('EpsBankTransfer.Eps', []);
    }

    public function process($eRemittanceIdentifier)
    {
        ob_start();
        $this->Eps->HandleConfirmationUrl($eRemittanceIdentifier);
        $contents = ob_get_clean();
        $this->set('contents', $contents);
    }

}
