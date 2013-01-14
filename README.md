# IceCast2 PHP API

## Overview
This is fully functional, easy-to-use, RESTful API interface for your icecast2-based radio station.
It's based on a popular Slim Framework which makes it very flexible and reliable solution.

Featues:
* Easy to configure.
* Integration with memcached for high performance.
* Rapid deployment within a minute.
* Multiple mountpoint support!
* Easy to extend.
* Different response types: JSON or XML.
* Mount fallback support!
* It is awesome!

It allows you to:
* Show number of listeners per mountpoint.
* Show current track per mountpoint with timestamp.
* Show last N tracks per mountpoiint alwo with their timestamps.
* Show total number of current listeners online.

## Install
It's never been so easy if you're using composer.
Just unpack it to your api's root directory, and install the dependencies:

```php composer.phar install```

## Configuration
After all dependencies are installed, it's the time to configure our new API.
Well, it's pretty simple, just edit the $config array:
```
//IceCast API Config
$config = array(
  'icecast_server_hostname'			   => 'radio.example.com', //icecast2 server hostname or IP
	'icecast_server_port'			   => 80, 
	'icecast_admin_username' 		   => 'admin', //admin username
	'icecast_admin_password' 		   => 'password', //admin password
	//If you have an event based mounts(e.g. for live broadcasting), 
	//you should configure fallback map below according to your icecast2 config file.
	//Read the docs for more info.
	'icecast_mount_fallback_map'               => array('live'        => 'nonstop',     // from => to
	                                                    'trance.live' => 'trance.nonstop',
	                                                    'house.live	  => 'house.nonstop'),
	'playlist_logfile' 		           => '/var/log/icecast2/playlist.log', // must be available for reading
	'use_memcached' 		           => true,                             // Enable memcached support: true || false
	'memcached' 			           => array('server'     => '127.0.0.1'
	                                                    'port'       => 11211, 
	                                                    'lifetime'   => 10, // Cache lifetime in seconds
 	                                                    'compressed' => 0), // compress data stored with memcached? 1 || 0. Requires zlib.
	'max_amount_of_history'			   => '20',      // max limit of requested items of playback history
	'xmlrootnode'			           => 'response' // Root node name for the response using XML.
);
```
## Mount Fallback map
I think every popular radiostation hosts a live broadcasts. But with all it's popularity, it comes with some problems, if you're using default Icecast2 fallback mechanic.
When live source hits the air, listeners are being automatically moved to its mountpoint, leaving the old nonstop mount completely empty.
In order to continue providing actual data to your API clients you need to detect when live broadcast is going up and alter your data "on-the-fly".

To bring this thing to work you need to configure Mount Fallback map according to your station's archeticture.

So, for example, if you have live(for DJs) mount called "live" with following configuration in icecast.xml:
```
<mount>
  <mount-name>/live</mount-name>
	<stream-name>MyRadio Main RJ Stream</stream-name>
  <fallback-mount>/myradio.nonstop</fallback-mount>
	<fallback-override>1</fallback-override>
	<mp3-metadata-interval>2048</mp3-metadata-interval>
	<password>pwd</password>
</mount>
```
Just bring the `icecast_mount_fallback_map` to the following state:
```
'icecast_mount_fallback_map'   => array('live' => 'myradio.nonstop'),
```
That' all. Now your API service will provide data from the live mount when it's active or from the one it's associated with, if it's down.

## Performance
Thanks to built-in memcached support your new api service has, quite literally,  unrival performance.

Here are some tests result:
Query: `ab -n 10000 -c 100 http://dev.tort.fm:81/history/tort.fm/7/xml/`
### Memcached ON
```
	Server Software:        nginx/0.7.67
	
	Document Path:          /history/tort.fm/7/xml/
	Document Length:        697 bytes
	
	Concurrency Level:      100
	Time taken for tests:   16.196 seconds
	Complete requests:      10000
	Failed requests:        0
	Write errors:           0
	Total transferred:      8620000 bytes
	HTML transferred:       6970000 bytes
	Requests per second:    617.44 [#/sec] (mean)
	Time per request:       161.958 [ms] (mean)
	Time per request:       1.620 [ms] (mean, across all concurrent requests)
	Transfer rate:          519.76 [Kbytes/sec] received
```
### Memcached OFF
```
	Server Software:        nginx/0.7.67
	
	Document Path:          /history/tort.fm/7/xml/
	Document Length:        682 bytes
	
	Concurrency Level:      100
	Time taken for tests:   37.076 seconds
	Complete requests:      10000
	Failed requests:        0
	Write errors:           0
	Total transferred:      8470000 bytes
	HTML transferred:       6820000 bytes
	Requests per second:    269.72 [#/sec] (mean)
	Time per request:       370.757 [ms] (mean)
	Time per request:       3.708 [ms] (mean, across all concurrent requests)
	Transfer rate:          223.10 [Kbytes/sec] received
```

All tests were made on the following server:
```
CPU	Intel Quad Xeon E3-1230 4 x 3.20 Ghz
RAM	12 GB
Web-server: nginx 0.7 with php5-fpm
```
Not bad, huh? Whatcha think?


## Demos
So, the best demo is the working project, huh?
This API is fully integrated and succesfuly working at the most popular russian 
online gaming station called "Tort.FM". It was developed for that project, eventually.

Here you go:
### Current listeners from tort.fm main mountpoint, xml response:
<http://dev.tort.fm/listeners/tort.fm/xml/>
### Current track from tort.fm main mountpoint, json response:
<http://dev.tort.fm/track/tort.fm/json/>
### Last 7 tracks from our trance channel, xml response:
<http://dev.tort.fm/history/tort.fm/7/xml/>
### Total listeners, xml response:
<http://dev.tort.fm/totalListeners/xml/>

## Extend
If you want to add your custom functionality, just create additional methods in icecast_api.php file using this template:
```
private function YourCustomMethodAction(array $args){
		return array('Hello' => 'Im a template for your custom methods.');
} 
```
Note: There is a strict rules applied to the names of your methods. It has to have the following format: {methodname}Action.

Then create new route block inside index.php file like this:
```
$app->get('/customMethod/:variable/:responseType(/)', function ($variable, $responseType) use ($icecastApi, $app) {

  $app->response()->header("Content-Type", "application/".$responseType);
  echo $icecastApi->Request('YourCustomMethod',array('your_var' => $variable))->Response($responseType);
	
})->conditions(array("responseType" => "(json|xml)"));
```
This is it. You can find these templates within the files aswell.
