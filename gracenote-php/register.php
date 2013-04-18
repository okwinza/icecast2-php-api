<?
include("Gracenote.class.php");
include('../config.php');

//You can get these after completing registration at https://developer.gracenote.com
if(!empty($config['gracenote']['userID'])){
	die('You already have an userID specified. No need for another.');
}

$api = new Gracenote\WebAPI\GracenoteWebAPI($config['gracenote']['clientID'], $config['gracenote']['clientTag'], $config['gracenote']['userID']);
$userID = $api->register();

if(!empty($userID)){
	echo "Your userID: ".$userID . '<br>';
	echo "Save this value in the config file and remove this file.";
}
?>