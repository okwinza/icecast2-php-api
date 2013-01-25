<?php
/*
/* @package IceCast2 PHP API
/* http://github.com/okwinza/icecast2-php-api
/* @author Okwinza
/* This work is licensed under the Creative Commons Attribution 3.0 Unported License. 
/* To view a copy of this license, visit http://creativecommons.org/licenses/by/3.0/ 
/* or send a letter to Creative Commons, 444 Castro Street, Suite 900, Mountain View, California, 94041, USA. 
*/

define('IN_APP', true);
require 'vendor/autoload.php';
require 'icecast_api.php';

//IceCast API Config
$config = array(
	'icecast_server_hostname' 		 => 'radio.example.com', //icecast2 server hostname or IP
	'icecast_server_port'			 => 80, 
	'icecast_admin_username' 		 => 'admin', //admin username
	'icecast_admin_password' 		 => 'password', //admin password
	//If you have an event based mounts(e.g. for live broadcasting), 
	//you should configure fallback map below according to your icecast2 config file.
	//Read the docs for more info.
	'icecast_mount_fallback_map' => array('live' => 'nonstop',  // from => to
									  'trance.live'  => 'trance.nonstop',
									  'house.live'	=> 'house.nonstop'),
	'playlist_logfile' 		 => '/var/log/icecast2/playlist.log', // must be available for reading
	'use_memcached' 		 => true, // using of the memcached: true | false
	'memcached' 			 => array('server' 		=> '127.0.0.1', 
									  'port' 		=> 11211, 
									  'lifetime' 	=> 10, // lifetime of the cache in seconds
									  'compressed'  => 0), // compress data stored with memcached? 1 or 0. Requires zlib.
	'max_amount_of_history'	 => '20', // max limit of requested items of playback history
	'xmlrootnode'			 => 'response' // Root node name for the response using XML.
);

//initiate Slim
$app = new \Slim\Slim();
//initiate IcecastApi model
$icecastApi = new icecastApi($config);

//Allow crossdomain requests
$app->response()->header("Access-Control-Allow-Origin", "*");

//No active mounts -- nothing to do here, shutting down...
$active_mounts = $icecastApi->listMounts(true);
if(empty($active_mounts)){
    // build response
    $response = array(
        'type' => '503',
        'message' => 'Unavailable'
    );

    // output response and exit.
    $app->halt(503, json_encode($response));
}


//Number of listeners of specified mountpoint.
//(string) :mount 		 => one of the existing mounts
//(string) :responseType => response type(json|xml)
$app->get('/listeners/:mount/:responseType(/)', function ($mount,$responseType) use (&$icecastApi, &$app) {
	
	$app->response()->header("Content-Type", "application/".$responseType);	//setting appropriate headers
	echo $icecastApi->Request('GetListeners',array('mount' => $mount))->Response($responseType); //returning response to the client
	
})->conditions(array("mount" => "(". implode('|',$icecastApi->listMounts()) .")", "responseType" => "(json|xml)")); // Only existing mounts are allowed


//Current track of specified mountpoint.
//(string) :mount 		 => one of the existing mounts
//(string) :responseType => response type(json|xml)
$app->get('/track/:mount/:responseType(/)', function ($mount,$responseType) use (&$icecastApi, &$app) {

	$app->response()->header("Content-Type", "application/".$responseType); //setting appropriate headers
	echo $icecastApi->Request('GetTrack',array('mount' => $mount))->Response($responseType); //returning response to the client
	
})->conditions(array("mount" => "(". implode('|',$icecastApi->listMounts()) .")", "responseType" => "(json|xml)"));


//Last tracks of specified mountpoint.
//(string) :mount 	     => one of the existing mounts
//(int)    :amount 		 => amount of tracks to retrieve
//(string) :responseType => response type(json|xml)
$app->get('/history/:mount/:amount/:responseType(/)', function ($mount,$amount,$responseType) use (&$icecastApi, &$app) {

	$app->response()->header("Content-Type", "application/".$responseType); //setting appropriate headers
	echo $icecastApi->Request('GetHistory',array('mount' => $mount , 'amount' => $amount))->Response($responseType); //returning response to the client
	
})->conditions(array("mount" => "(". implode('|',$icecastApi->listMounts()) .")", "amount" => "\d+" , "responseType" => "(json|xml)"));


//Total listeners
//(string) :responseType => response type(json|xml)
$app->get('/totalListeners/:responseType(/)', function ($responseType) use (&$icecastApi, &$app) {

	$app->response()->header("Content-Type", "application/".$responseType); //setting appropriate headers
	echo $icecastApi->Request('GetTotalListeners',array())->Response($responseType); //returning response to the client
	
})->conditions(array("responseType" => "(json|xml)"));




//Custom method template
/* 
$app->get('/customMethod/:responseType(/)', function ($responseType) use (&$icecastApi, &$app) {

	$app->response()->header("Content-Type", "application/".$responseType);
	echo $icecastApi->Request('YourCustomMethod',array())->Response($responseType);
	
})->conditions(array("responseType" => "(json|xml)")); 
*/


$app->notFound(function () use (&$app) {

    // build response
    $response = array(
        'type' => '400',
        'message' => 'Bad request'
    );

    // output response and exit
    $app->halt(400, json_encode($response));
});

$app->run();

?>
