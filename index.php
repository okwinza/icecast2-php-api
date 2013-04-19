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
require 'config.php';

//initiate Slim
$app = new \Slim\Slim();
//initiate IcecastApi model
$icecastApi = new icecastApi($config);


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
$app->get('/:mount/listeners/:responseType(/)', function ($mount,$responseType) use ($icecastApi, $app) {
	
	$app->response()->header("Content-Type", "application/".$responseType);	//setting appropriate headers
	echo $icecastApi->Request('GetListeners',array('mount' => $mount))->Response($responseType); //returning response to the client
	
})->conditions(array("mount" => "(". implode('|',$icecastApi->listMounts()) .")", "responseType" => "(json|xml)")); // Only existing mounts are allowed


//Current track of specified mountpoint.
//(string) :mount 		 => one of the existing mounts
//(string) :responseType => response type(json|xml)
$app->get('/:mount/track/:responseType(/)', function ($mount,$responseType) use ($icecastApi, $app) {

	$app->response()->header("Content-Type", "application/".$responseType); //setting appropriate headers
	echo $icecastApi->Request('GetTrack',array('mount' => $mount))->Response($responseType); //returning response to the client
	
})->conditions(array("mount" => "(". implode('|',$icecastApi->listMounts()) .")", "responseType" => "(json|xml)"));


//Last tracks of specified mountpoint.
//(string) :mount 	     => one of the existing mounts
//(int)    :amount 		 => amount of tracks to retrieve
//(string) :responseType => response type(json|xml)
$app->get('/:mount/history/:amount/:responseType(/)', function ($mount,$amount,$responseType) use ($icecastApi, $app) {

	$app->response()->header("Content-Type", "application/".$responseType); //setting appropriate headers
	echo $icecastApi->Request('GetHistory',array('mount' => $mount , 'amount' => $amount))->Response($responseType); //returning response to the client
	
})->conditions(array("mount" => "(". implode('|',$icecastApi->listMounts()) .")", "amount" => "\d+" , "responseType" => "(json|xml)"));


//Total listeners
//(string) :responseType => response type(json|xml)
$app->get('/totalListeners/:responseType(/)', function ($responseType) use ($icecastApi, $app) {

	$app->response()->header("Content-Type", "application/".$responseType); //setting appropriate headers
	echo $icecastApi->Request('GetTotalListeners',array())->Response($responseType); //returning response to the client
	
})->conditions(array("responseType" => "(json|xml)"));




$app->get('/album/:artist/:song(/)', function ($artist, $song) use ($icecastApi, $app) {


	$img = $icecastApi->Request('GetAlbumArt',array('artist' => $artist, 'song' => $song), true)->Response(); //returning response to the client
	$app->response()->header("Content-Type", "image/jpeg"); //setting appropriate headers
	$app->response()->header("Content-Length", filesize($img));
	
	readfile($img);

});

$app->get('/album/:artist(/)', function ($artist) use ($icecastApi, $app) {


	$img = $icecastApi->Request('GetArtistArt',array('artist' => $artist), true)->Response(); //returning response to the client
	$app->response()->header("Content-Type", "image/jpeg"); //setting appropriate headers
	$app->response()->header("Content-Length", filesize($img));
	
	readfile($img);

});


$app->post('/event/listener/OnConnect(/)', function () use ($icecastApi, $app) {
	
	$post = $app->request()->post();
	$data = '';
	foreach($post as $key => $val){
		$data .= $key.' : '.$val . PHP_EOL;	
	}
	file_put_contents('/var/www/home/dev.tort.fm/listener-c.txt',$data);
	//echo $data;
	
	
	if($icecastApi->Request('AuthListenerOnConnect',$app->request()->post())->Response()){
		//$app->response()->header($config['icecast_listener_auth_header_title'], $config['icecast_listener_auth_header_value']);
	}else{
		//$app->response()->header("icecast-auth-message", $config['icecast_listener_auth_reject_reason']);
	}
	
}); 


$app->post('/event/listener/OnDisconnect(/)', function () use ($icecastApi, $app) {
	
	$post = $app->request()->post();
	$data = '';
	foreach($post as $key => $val){
		$data .= $key.' : '.$val . PHP_EOL;	
	}
	file_put_contents('/var/www/home/dev.tort.fm/listener-dc.txt',$data);
	
	//$icecastApi->Request('ListenerOnDisconnect',$app->request()->post());
	
});

$app->post('/event/source/OnConnect(/)', function () use ($icecastApi, $app) {
	
	$post = $app->request()->post();
	$data = '';
	foreach($post as $key => $val){
		$data .= $key.' : '.$val . PHP_EOL;	
	}
	file_put_contents('/var/www/home/dev.tort.fm/source-c.txt',$data);
	
	//$icecastApi->Request('SourceOnConnect',$app->request()->post());
	
}); 

$app->post('/event/source/OnDisconnect(/)', function () use ($icecastApi, $app) {
	
	$post = $app->request()->post();
	$data = '';
	foreach($post as $key => $val){
		$data .= $key.' : '.$val . PHP_EOL;	
	}
	file_put_contents('/var/www/home/dev.tort.fm/source-dc.txt',$data);
	
	//$icecastApi->Request('SourceOnDisconnect',$app->request()->post());
	
}); 

 
 



//Custom method template
/* 
$app->get('/customMethod/:responseType(/)', function ($responseType) use ($icecastApi, $app) {

	$app->response()->header("Content-Type", "application/".$responseType);
	echo $icecastApi->Request('YourCustomMethod',array())->Response($responseType);
	
})->conditions(array("responseType" => "(json|xml)")); 
*/


$app->notFound(function () use ($app) {

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