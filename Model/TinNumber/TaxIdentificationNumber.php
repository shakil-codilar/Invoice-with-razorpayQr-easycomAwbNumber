<?php

namespace Codilar\QrCode\Model\TinNumber;

class TaxIdentificationNumber{
    public function getTinNumber($stateCode){
        $indianStates = [
            'AP' => 37,
            'AR' => 12,
            'AS' => 18,
            'BR' => 10,
            'CT' => 22,
            'GA' => 30,
            'GJ' => 24,
            'HR' => 06,
            'HP' => 02,
            'JK' => 01,
            'JH' => 20,
            'KA' => 29,
            'KL' => 32,
            'MP' =>  23,
            'MH' => 27,
            'MN' => 14,
            'ML' => 17,
            'MZ' => 15,
            'NL' => 13,
            'OR' => 21,
            'PB' => 03,
            'SK' => 11,
            'RJ' => 8,
            'TN' =>  33,
            'TG' => 36,
            'TR' => 16,
            'UP' => 9,
            'UT' => 05,
            'WB' => 19,
            'AN' => 35,
            'CH' => 4,
            'DN' => 26,
            'DD' => 26,
            'LD' => 31,
            'DL' => 07,
            'PY' => 34];

        $tinNumber = 0;
        foreach($indianStates as $key=>$data){
            if ($key == $stateCode){
                $tinNumber = $data;
            }
        }
        return$tinNumber;
    }
}
