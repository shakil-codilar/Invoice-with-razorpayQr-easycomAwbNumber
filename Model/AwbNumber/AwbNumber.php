<?php

namespace Codilar\QrCode\Model\AwbNumber;

class AwbNumber
{
    public function getAwbNumber($orderId)
    {
        $curl = curl_init();
        $api_key = $this->getAuthentication();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.easyecom.io/Carriers/getTrackingDetails?api_token='.$api_key.'&reference_code='.$orderId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $orderResponse = curl_exec($curl);

        curl_close($curl);

        //convert string response into array
        $result = '';
        $ecomData = array();
        $arr = array();
        $j = 0;
        for ($i = 0; $i < count(str_split($orderResponse)); $i++) {

            //strings are converted with combined strings/word
            if(preg_match("/[A-Za-z0-9 -]+/", $orderResponse[$i])) {
                $result .= $orderResponse[$i];
            }

            //when these conditions are true create a that combined strings(a word) into an array index
            if ($orderResponse[$i] == '"' || $orderResponse[$i] == ',' || $orderResponse[$i] == '{' || $orderResponse[$i] == '}' || $orderResponse[$i] == ':' ||  $orderResponse[$i] =='"\"' || $orderResponse[$i] ==' ') {
                $arr[$j] = $result;
                $result = '';
                $j++;
            }
        }

        //removed null array values
        foreach($arr as $data)
        {
            if($data == '')
            {
                unset($data);
            }
        }

        //array is re-indexed after removing null values
        $reindexed_arr = array_values(array_filter($arr));
        for ($z=0;$z<count($reindexed_arr);$z++) {
            if($reindexed_arr[$z]=='awbNumber' || $reindexed_arr[$z]=='carrierName' || $reindexed_arr[$z]=='orderDate' || $reindexed_arr[$z]=='invoiceDate'){
                $ecomData[] = $reindexed_arr[$z+1];
            }
        }
        return $ecomData;
    }

    private function getAuthentication(){
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.easyecom.io/getApiToken',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                "email" => $email,
                "password" => $password
            )
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        //convert string response into array
        $result = '';
        $arr = array();
        $j = 0;
        for ($i = 0; $i < count(str_split($response)); $i++) {

            //strings are converted with combined strings/word
            if(preg_match("/[A-Za-z0-9]+/", $response[$i])) {
                $result .= $response[$i];
            }

            //when these conditions are true create a that combined strings(a word) into an array index
            if ($response[$i] == '"' || $response[$i] == ',' || $response[$i] == '{' || $response[$i] == '}' || $response[$i] == ':') {
                $arr[$j] = $result;
                $result = '';
                $j++;
            }
        }

        //removed null array values
        foreach($arr as $data)
        {
            if($data == '')
            {
                unset($data);
            }
        }

        //array is re-indexed after removing null values
        $reindexed_arr = array_values(array_filter($arr));
        for ($z=0;$z<count($reindexed_arr);$z++) {

            if($reindexed_arr[$z]=='apitoken'){
                $token = $reindexed_arr[$z+1];
            }
        }
        return $token;
    }
}
