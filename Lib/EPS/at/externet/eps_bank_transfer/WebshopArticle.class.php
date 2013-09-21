<?php

namespace at\externet\eps_bank_transfer;
require_once "functions.php";

class WebshopArticle
{

    public $Name;
    public $Count;
    public $Price;

    public function __construct($name, $count, $price)
    {
        $this->Name = $name;
        $this->Count = $count;
        $this->SetPrice($price);
    }

    public function SetPrice($value)
    {
        $this->Price = FormatMonetaryXsdDecimal($value);
    }

}
?>
