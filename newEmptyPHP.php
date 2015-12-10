<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
	// can only be run by root user so has to be run from cron
// define('CLI_SCRIPT', true);
require_once('/var/www/html/admissions/config.php');
require_once($CFG->dirroot.'/google/lib.php');
require_once($CFG->dirroot.'/google/gauth.php');
require_once "$CFG->dirroot/google/google-api-php-client/autoload.php";
        echo 'privatekey = ' . $privatekey;
        die;


$admissions = new admissions();
$admissions->client->setRedirectUri('http://localhost');
$files = array(
    'AdmQuest.csv' => '12pFeoGegBdp1bWisE4k81HBJDtdrzYbyByYwLgwZBgY', 
    'mltest.csv' => '0At-LjN6v5M_DdGVoRHp5bTdGM1JPWUl0ZGVLd19VeHc', 
    'musSection.csv' => '1rB_St9T077KZs7fhmNWl93Lu6iCCf5Aoo6gbvIvUKFY',
    'admGrades.csv' => '1jY-1zdOd797zvbaFVlPvruepnP9itMznlFVLRr2u-ds',
    'katieDeposited.csv' => '1fFDQv1Ugf2A_1RNRDyR-2FVWPwdcncgT_W_CUwQI4l4', 
    'katieDepositedMusic.csv' => '1Hy4OU3nHI2QwXaW2rTtBBxStn5KaWrgXcvjhCDPXsi4'
    );

foreach ($files as $title => $id) {
    $getfile = '/var/lib/mysql/admssions/' . $title;

    //Update a file
    $file = new Google_Service_Drive_DriveFile();
    $data = file_get_contents($getfile);
    $additonalParams = array($data);

    $createdFile = $admissions->service->files->update($id, $file, $additonalParams);
}	


class admissions {
    
    public function __construct() {
        $service_name = 'drive';
        $this->client = new Google_Client();
        $this->client->addScope("https://www.googleapis.com/auth/drive.file");
        $this->service = new Google_Service_Drive($this->client);
        $this->auth = service_account($this->client);
    }
    
}
?>
