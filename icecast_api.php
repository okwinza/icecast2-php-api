<?
/*
/* @package IceCast2 PHP API
/* http://github.com/okwinza/icecast2-php-api
/* @author Okwinza
/* This work is licensed under the Creative Commons Attribution 3.0 Unported License. 
/* To view a copy of this license, visit http://creativecommons.org/licenses/by/3.0/ 
/* or send a letter to Creative Commons, 444 Castro Street, Suite 900, Mountain View, California, 94041, USA. 
*/
if(!defined('IN_APP')){
	die('Hacking Attempt!');
}

class IcecastApi {

	private $config = array();
	private $result = null;
	private $_memcache = null;
	private $_db = null;
	private $icecast_xml = null;

	function __construct(array $config){
		
		$this->config = $config;
		if($this->config['use_memcached']) $this->MemcachedInit();
		if($this->config['use_db']) $this->GetDB();
		$this->icecast_xml = $this->Request('getDataFromIcecast')->result;
		if(empty($this->icecast_xml)) die('Failed to connect to Icecast Server.');

	}
	//Preparing response according to its type.
	// @param (array) $type
	// @returns formatted xml/json or just "as is".
	public function Response($type = null){
		switch($type){
			case 'xml':
				return $this->array_to_xml($this->result);
				break;
			case 'json':
				return $this->array_to_json($this->result);
				break;
			default:
				return $this->result;
				break;
		}
	}
	
	
	//main factory
	public function Request($method, array $args = array(), $memcachedOverride = false){
		
		if(!empty($args['mount']) && !empty($this->config['icecast_mount_fallback_map'][$args['mount']])){
			$args['mount'] = (!in_array($args['mount'],$this->listMounts(true))) ? $this->config['icecast_mount_fallback_map'][$args['mount']] : $args['mount'];		
		}
		
		$this->result = (($this->config['use_memcached'] === true) && ($memcachedOverride === false)) ? $this->WithMemcached($method , $args) :  $this->{$method . 'Action'}($args);
		return $this;
	}
	
	public function listMounts($only_active = false){
		
		$xml = simplexml_load_string($this->icecast_xml);
		if($only_active){
			$xml = $xml->xpath("/icestats/source[source_ip != '']/@mount");	
		}else{
			$xml = $xml->xpath("/icestats/source/@mount");	
		}
		// to array
		$json = json_encode($xml);
		$array = json_decode($json,TRUE);
		
		$list = array();
		foreach($array as $key => $item){
			$list[] = str_replace('/','',$item['@attributes']['mount']);
		}
		return $list;
	}
	
	
	//Memcached wrapper.
	private function WithMemcached($method, array $args){
		
		$key = $_SERVER['SERVER_NAME'].'_'.$method;
		
		foreach($args as $k => $val){
			$key .= '_'.$val;
		}
		$data = $this->_memcache->get($key);
		
		if(empty($data)){
			$data = $this->{$method . 'Action'}($args);
			$this->_memcache->set($key,$data,$this->config['memcached']['compressed'],$this->config['memcached']['lifetime']);
		}
		
		return $data;
	}
	
	
		
	
	/* 	
	Getting number of listeners for given mountpoint
	
	@param array $args
	@return array 
	*/
	
	private function GetListenersAction(array $args){	
		extract($args); // $mount
		
		$xml = simplexml_load_string($this->icecast_xml);
		$xml = $xml->xpath("/icestats/source[@mount='/".$mount."']/listeners");
		
		//to array
		$json = json_encode($xml);
		$array = json_decode($json,TRUE);
		
		return array('listeners' => $array[0][0]);
	}
	
	/* 	
	Getting total number of listeners
	
	@param array $args - empty array
	@return array 
	*/		
	private function GetTotalListenersAction(array $args){
		
		$xml = simplexml_load_string($this->icecast_xml);
		$xml = $xml->xpath("/icestats/listeners");
		
		$json = json_encode($xml);
		$array = json_decode($json,TRUE);
		
		return array('totalListeners'=>$array[0][0]);
	}
	
	/* 	
	Getting the current track and its timestamp for given $mount
	
	@param array $args[$mount]
	@return array 
	*/
	private function GetTrackAction(array $args){
		extract($args); // $mount
		
		$data = $this->GetHistoryAction(array('mount'=> $mount,'amount'=>1)); //using already defined method.
		return $data;
	}	
	
	/* 	
	Parsing the logfile, returning an array of with specified $amount of last tracks for given $mount
	
	@param array $args[string $mount, int $amount] 
	@return array 
	*/
	private function GetHistoryAction(array $args){
		extract($args); // $mount, $amount
		$amount = ($amount > $this->config['max_amount_of_history']) ? $this->config['max_amount_of_history'] : $amount; // checking if number of requested songs is lower than max
		
		$grab_lines = intval($amount * pow(count($this->listMounts()),1.2)); // amount of lines to work with
		
		$last_lines = $this->GetLastLinesFromFile($this->config['playlist_logfile'], $grab_lines); //gettins required number of lines from the logfile
		$last_lines = explode("\n",$last_lines);
		array_pop($last_lines); // deleting last empty line
		$last_lines = array_reverse($last_lines); // desc. order
		
		$line_parsed = array();
		$result_array = array();
		$i = 0;
		
		foreach($last_lines as $key => $line){
			$line_parsed = explode("|",$line);
			/*
			$line_parsed[0] - timestamp of the song, i.e. 							  14/Apr/2013:13:52:58 +0400
			$line_parsed[1] - mountpoint of the song, i.e. 							  /trance
			$line_parsed[2] - icecast's ID of the song(or something like that), i.e.  36
			$line_parsed[3] - full title of the song, i.e. 							  Pakito - You Wanna Rock
			*/
			if($line_parsed[1] == "/".$mount){
			
				if(empty($line_parsed[3])) continue; //empty song title, skipping
				
				if($i < $amount){
					$song_parts = explode("-",htmlspecialchars($line_parsed[3])); // exploding to artist and title
					
					$result_array[$i]['track'] = htmlspecialchars($line_parsed[3]); 
					
					$result_array[$i]['title']  = 		(!empty($song_parts[1])) ? trim($song_parts[1]) : 'Unknown Title';  //only title, i.e. You Wanna Rock 
					$result_array[$i]['artist'] = 		(!empty($song_parts[0])) ? trim($song_parts[0]) : 'Unknown Artist'; //only artist, i.e. Pakito
					
					$result_array[$i]['timestamp'] = strtotime($line_parsed[0]); //unixtime
					$result_array[$i]['album_art_url'] = 		'http://'.$_SERVER['SERVER_NAME'].'/cover/'.urlencode($result_array[$i]['artist']).'/'.urlencode($result_array[$i]['title']);
					$result_array[$i]['artist_image_url'] = 	'http://'.$_SERVER['SERVER_NAME'].'/cover/'.urlencode($result_array[$i]['artist']);
					
					$i++;
				}else break;
			}
		}
		return $result_array;
	}
	
	
	private function GetAlbumArtAction(array $args){
		require_once('gracenote-php/Gracenote.class.php');
		
		if(!is_writable($this->config['album_art_folder'])){
			die('album art folder is not writable.');
		}
		
		$default_img = $this->config['default_storage_folder'] . '404.jpg';
		$filename = $this->config['album_art_folder'] . md5($args['artist'] . $args['song']) . '.jpg';
		
		if(file_exists($filename)){  //found in cache, returning.
			
			if(filesize($filename) == 0){
				return $default_img;
			}
			
			return $filename;
		}
		
		
		if(!empty($this->config['gracenote']['userID'])){
			$gracenote_api = new Gracenote\WebAPI\GracenoteWebAPI($this->config['gracenote']['clientID'], $this->config['gracenote']['clientTag'], $this->config['gracenote']['userID']);
		}else{
			die('Get your Gracenote userID via <a href="/gracenote-php/register.php">register.php</a> to continue.');
		}
		$result = array();
		
		//querying the GraceNote API
		try
		{	
			$result = $gracenote_api->searchTrack($args['artist'], '',$args['song'], Gracenote\WebAPI\GracenoteWebAPI::BEST_MATCH_ONLY);
		}
		catch( Exception $e )
		{
			return $default_img; // something went wrong, returning dummy picture
		}
		
		if(empty($result[0]['album_art_url'])){
			
			touch($filename);	
			return $default_img; // something went wrong, returning dummy picture
		}
		
		$album_art = file_get_contents($result[0]['album_art_url']);
		
		if(touch($filename)){
			file_put_contents($filename , $album_art);
		}else{
			return $default_img; // something went wrong, returning dummy picture;
		}
		
		return $filename;
	}


	
	private function GetArtistArtAction(array $args){
		require_once('gracenote-php/Gracenote.class.php');
		
		if(!is_writable($this->config['artist_art_folder'])){
			die('artist art folder is not writable.');
		}
		
		$default_img = $this->config['default_storage_folder'] . '404.jpg';
		$filename = $this->config['artist_art_folder'] . md5($args['artist']) . '.jpg';
		
		if(file_exists($filename)){  //found in cache, returning.
			
			if(filesize($filename) == 0){
				return $default_img;
			}
			
			return $filename;
		}
		
		
		
		if(!empty($this->config['gracenote']['userID'])){
			$gracenote_api = new Gracenote\WebAPI\GracenoteWebAPI($this->config['gracenote']['clientID'], $this->config['gracenote']['clientTag'], $this->config['gracenote']['userID']);
		}else{
			die('Get your Gracenote userID via <a href="/gracenote-php/register.php">register.php</a> to continue.');
		}
		$result = array();
		
		//querying the GraceNote API
		try
		{	
			$result = $gracenote_api->searchArtist($args['artist'], Gracenote\WebAPI\GracenoteWebAPI::BEST_MATCH_ONLY);
		}
		catch( Exception $e )
		{
			return $default_img; // something went wrong, returning dummy picture
		}
		
				
		if(empty($result[0]['artist_image_url'])){	//empty response, probably wrong artist name. Caching.
			
			touch($filename);	
			return $default_img;  // something went wrong, returning dummy picture	
		}
		
		$artist_art = file_get_contents($result[0]['artist_image_url']);
		
		if(touch($filename)){
			file_put_contents($filename , $artist_art);
		}else{
			return $default_img; // something went wrong, returning dummy picture;
		}
		
		return $filename;
	}	
	
	
	
	
	/* 
	private function YourCustomMethodAction(array $args){
	
		return array('Hello' => 'Im a template for your custom methods.');
	} 
	*/

	
	private function MemcachedInit(){
		$this->_memcache = new Memcache();
		$this->_memcache->connect($this->config['memcached']['host'], $this->config['memcached']['port']);	
	}
	
	private function GetDB(){
		//setup your db interface here...
		//$this->_db = your DB object.
	}	
	
	
	
	private function getDataFromIcecastAction(){
	
		$process = curl_init($this->config['icecast_server_hostname'].':'.$this->config['icecast_server_port'].'/admin/stats');

		curl_setopt($process, CURLOPT_USERPWD, $this->config['icecast_admin_username'] . ":" . $this->config['icecast_admin_password']);
		curl_setopt($process, CURLOPT_TIMEOUT, 5);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		
		return curl_exec($process);		
	}
	
	
	private function array_to_xml(array $array, $xml=null){

        if ($xml == null)
        {
            $xml = simplexml_load_string("<?xml version='1.0' encoding='UTF-8'?><". $this->config['xmlrootnode'] ."/>");
        }
  
        foreach($array as $key => $value)
        {
            if (is_numeric($key))
            {
				$key = 'item';
            }
			$value = mb_convert_encoding($value, 'UTF-8');
            $key = preg_replace('/[^a-z]/i', '', $key);
            if (is_array($value))
            {
                $node = $xml->addChild($key);
                $this->array_to_xml($value, $node);
            }
            else
            {
                $xml->addChild($key,$value);
            }
        }
        return $xml->asXML();
    }
	
	private function array_to_json(array $array){
		return json_encode($array);
	}
	
	private function GetLastLinesFromFile($filename, $lines)
	{
		$offset = -1;
		$c = '';
		$read = '';
		$i = 0;
		$fp = @fopen($filename, "r");
		while( $lines && fseek($fp, $offset, SEEK_END) >= 0 ) {
			$c = fgetc($fp);
			if($c == "\n" || $c == "\r"){
				$lines--;
			}
			$read .= $c;
			$offset--;
		}
		fclose ($fp);
		return strrev(rtrim($read,"\n\r"));
	}
	
	
	
}



?>
