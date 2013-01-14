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
	private $icecast_xml = null;

	function __construct(array $config){
		
		$this->config = $config;
		$this->icecast_xml = $this->getDataFromIcecast();
		if(empty($this->icecast_xml)) die('Failed to connect to Icecast Server.');
		if($this->config['use_memcached']) $this->MemcachedInit();

	}
	//Preparing response according to its type.
	// @param (array) $type
	// @returns xml or json
	public function Response($type){
		switch($type){
			case 'xml':
				return $this->array_to_xml($this->result);
			break;
			case 'json':
				return $this->array_to_json($this->result);
			break;		
		}
	}
	
	public function Request($method,array $args){
		
		if(!empty($args['mount']) && !empty($this->config['icecast_mount_fallback_map'][$args['mount']])){
			$args['mount'] = (!in_array($args['mount'],$this->listMounts(true))) ? $this->config['icecast_mount_fallback_map'][$args['mount']] : $args['mount'];		
		}
		
		$this->result = ($this->config['use_memcached'] === true) ? $this->WithMemcached($method , $args) :  $this->{$method . 'Action'}($args);
		return $this;
	}
	
	//Memcached wrapper.
	private function WithMemcached($method, array $args){
		
		$key = $method;
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
	private function YourCustomMethodAction(array $args){
	
		return array('Hello' => 'Im a template for your custom methods.');
	} 
	*/
	
	
	/* 	
	Getting number of listeners for given mountpoint
	
	@param array $args
	@return array 
	*/
	
	private function GetListenersAction(array $args){	
		extract($args); // $mount
		
		$xml = simplexml_load_string($this->icecast_xml);
		$xml = $xml->xpath("/icestats/source[@mount='/".$mount."']/listeners");
		
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
		$amount = ($amount > $this->config['max_amount_of_history']) ? $this->config['max_amount_of_history'] : $amount;
		
		$grab_lines = intval($amount * pow(count($this->listMounts()),1.2)); // amount of lines to grab
		
		$last_lines = $this->GetLastLinesFromFile($this->config['playlist_logfile'], $grab_lines);
		$last_lines = explode("\n",$last_lines);
		array_pop($last_lines); // deleting last empty line
		$last_lines = array_reverse($last_lines); // desc. order
		
		$line_parsed = array();
		$result_array = array();
		$i = 0;
		foreach($last_lines as $key => $line){
			$line_parsed = explode("|",$line);
			if($line_parsed[1] == "/".$mount){
				if($i < $amount){
					$result_array[$i]['track'] 	 = $this->filter_string($line_parsed[3]); // getting rid of special chars in the title
					$result_array[$i]['timestamp'] = strtotime($line_parsed[0]); 
					$i++;
				}else break;
			}
		}
		return $result_array;
	}	
	
	private function MemcachedInit(){
		$this->_memcache = new Memcache();
		$this->_memcache->pconnect($this->config['memcached']['server'], $this->config['memcached']['port']);	
	}
	
	public function listMounts($only_active = false){
		
		$xml = simplexml_load_string($this->icecast_xml);
		if($only_active){
			$xml = $xml->xpath("/icestats/source[source_ip != '']/@mount");	
		}else{
			$xml = $xml->xpath("/icestats/source/@mount");	
		}
		
		$json = json_encode($xml);
		$array = json_decode($json,TRUE);
		
		$list = array();
		foreach($array as $key => $item){
			$list[] = str_replace('/','',$item['@attributes']['mount']);
		}
		return $list;
	}

	
	
	private function getDataFromIcecast(){
		
		$process = curl_init($this->config['icecast_server_hostname'].':'.$this->config['icecast_server_port'].'/admin/stats');

		curl_setopt($process, CURLOPT_USERPWD, $this->config['icecast_admin_username'] . ":" . $this->config['icecast_admin_password']);
		curl_setopt($process, CURLOPT_TIMEOUT, 5);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		
		return curl_exec($process);		
	}
	
	private function array_to_xml(array $array, $xml=null){

        if ($xml == null)
        {
            $xml = simplexml_load_string("<?xml version='1.0'?><". $this->config['xmlrootnode'] ."/>");
        }
  
        foreach($array as $key => $value)
        {
            if (is_numeric($key))
            {
				$key = 'item';
            }
        
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
	
	private function filter_string($string){
		$search = array("'","&","@","\"");
		return str_replace($search,"",$string);
	}
	
	
	
}



?>
