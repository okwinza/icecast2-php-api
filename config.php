<?
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

?>