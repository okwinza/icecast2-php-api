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
	'icecast_server_hostname' 		 => 'radio.example.com', //icecast2 server hostname or IP
	'icecast_server_port'			 => 80, 
	'icecast_admin_username' 		 => 'admin', //admin username
	'icecast_admin_password' 	     => 'hackme', //admin password
	
	
	//unused
	'icecast_listener_auth_header_title'			=> 'icecast-auth-user',
	'icecast_listener_auth_header_value'			=> '1',
	'icecast_listener_auth_header_reject_reason' 	=> 'Rejected',
	
	//If you have an event based mounts(e.g. for live broadcasting), 
	//you should configure fallback map below according to your icecast2 config file.
	//Read the docs for more info.
	'icecast_mount_fallback_map' => array('live' => 'nonstop',  // from => to
									  'trance'  => 'trance.nonstop',
									  'house'	=> 'house.nonstop'),
									  
	'playlist_logfile' 		 => '/var/log/icecast2/playlist.log', // must be available for reading
	
	'use_memcached' 		 => false, // Enable memcached support: true | false
	'use_db'				 => false, // Enable db support: true | false  (unused atm)

	'memcached' 			 => array('host' 		=> '127.0.0.1', 
									  'port' 		=> 11211, 
									  'lifetime' 	=> 5, // lifetime of the cache in seconds
									  'compressed'  => 0), // compress data stored with memcached? 1 or 0. Requires zlib.
									  
	'db'					=> array('host'			=> '127.0.0.1',
									 'port'			=> 3306,
									 'user'			=> 'dbuser',
									 'password'		=> 'dbpassword'),			
	'max_amount_of_history'	 => '20', // max limit of requested items of playback history
	'xmlrootnode'			 => 'response', // Root node name for the response using XML.
	'album_art_folder'		 => getcwd().'/dev.tort.fm/storage/albums/',   // cache folder for albums art images. With trailing slash. Normally, u shouldn't change this.
	'gracenote'				 => array('clientID' 	=> '',
									  'clientTag' 	=> '',
									  'userID' 		=> '',
								),
	'default_storage_folder' => getcwd().'/storage/default/', // default static folder. Normally, u shouldn't change this.
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

Query: `ab -n 10000 -c 100 http://api.example.com/live/track/xml/`

### Memcached ON
```
	Server Software:        nginx/0.7.67
	
	Document Path:          /live/track/xml/
	Document Length:        697 bytes
	
	Concurrency Level:      100
	Time taken for tests:   0.785 seconds
	Complete requests:      1000
	Failed requests:        0
	Write errors:           0
	Total transferred:      463000 bytes
	HTML transferred:       277000 bytes
	Requests per second:    1274.19 [#/sec] (mean)
	Time per request:       78.481 [ms] (mean)
	Time per request:       0.785 [ms] (mean, across all concurrent requests)
	Transfer rate:          576.12 [Kbytes/sec] received
```
### Memcached OFF
```
	Server Software:        nginx/0.7.67
	
	Document Path:          /live/track/xml/
	Document Length:        682 bytes
	

	Concurrency Level:      100
	Time taken for tests:   1.040 seconds
	Complete requests:      1000
	Failed requests:        0
	Write errors:           0
	Total transferred:      439000 bytes
	HTML transferred:       253000 bytes
	Requests per second:    961.51 [#/sec] (mean)
	Time per request:       104.003 [ms] (mean)
	Time per request:       1.040 [ms] (mean, across all concurrent requests)
	Transfer rate:          412.21 [Kbytes/sec] received
```

All tests were made on the following server:
```
CPU	Intel Quad Xeon E3-1230 4 x 3.20 Ghz
RAM	12 GB
Web-server: nginx 0.7 with php5-fpm
```
1274 RPS against 961.
Not bad, huh? Whatcha think?


## Demos
So, the best demo is the working project, right?
This API is fully integrated and succesfuly working at the most popular russian 
online gaming station called "Tort.FM". It was developed for that project, eventually.

Here you go:
### Current listeners from tort.fm main mountpoint, xml response:
<http://api.tort.fm/radio/tort.fm/listeners/xml/>
### Current track from tort.fm main mountpoint, json response:
<http://api.tort.fm/radio/tort.fm/song/json/>
### Last 7 tracks from our trance channel, xml response:
<http://api.tort.fm/radio/tort.fm/history/7/xml/>
### Total listeners, xml response:
<http://api.tort.fm/radio/TotalListeners/xml/>

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
