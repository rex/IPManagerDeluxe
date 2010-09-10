<?php 

/*
 * SET ERROR REPORTING LEVEL
 */				  /***/
$level		=		'0'		;
if(empty($level) || ($level == '')) {
	define('ERROR_LEVEL',E_ALL);
} else {
	define('ERROR_LEVEL',$level);
}
error_reporting(ERROR_LEVEL);

/*
 * Other configurable options
 */
define('CURRENT_DATE_FORMAT'	,	"Y-m-d H:i:s"	);
define('ERROR_LOG_LOCATION'		,	'error_log.log'	);
define('DEBUG_DEFAULT'			,		0			); // Toggles global log debugging. If true(1), all provided information will be logged. False(0) will disable logging.

class Error {
	
	public function __construct()
	{
		$this->error_level = ERROR_LEVEL;
		$this->log_location = ERROR_LOG_LOCATION;
		$this->date_format = CURRENT_DATE_FORMAT;
	}
	
	public function log($message,$function=null,$line=null,$die=0)
	{
		$message = date(CURRENT_DATE_FORMAT);
		foreach(func_get_args() as $k=>$v) {
			if(!empty($v) || ($v != '')) {
				if($k != 3) {
					$message .= ' - ' . $v;
				}
			}
		}
		if(!error_log($message . "\r\n",3,ERROR_LOG_LOCATION)) {
			die("Log entry unable to be created. Please check all settings.");
		}
		if($die!=1) {
			trigger_error($message);
		} else if($die = 1) {
			die($message);
		}
	}
	
	public function fullHalt($message,$function=null,$line=null)
	{
		if(!error::log($message,$function,$line,1)) {
			die($message);
		}
	}
}

class DB {
	
	public function __construct()
	{
		$this->error = new Error;
		$this->user = 'root';
		$this->pass = '';
		$this->host = 'localhost';
		$this->target_db = 'ip';
		try {
			if(!$this->_connect(true)) {
				throw new Exception("Connection to '$this->host' failed. Error: " . self::getError());
			}
			if(!$this->_select($this->target_db)) {
				throw new Exception("Connection to database '$this->target_db' failed. Error: " . self::getError());
			}
		} catch (Exception $e) {
			error::fullHalt($e->getMessage(),__FUNCTION__,__LINE__);
		}
		
	}

	final private function _connect()
	{
		if(mysql_connect($this->host,$this->user,$this->pass)) {
			return true;
		}
		return false;
	}
	
	final private function _select($db)
	{
		if(mysql_select_db($db)) {
			return true;
		}
		return false;
	} 
	
	final public function prepare($var)
	{
		if(is_array($var)) {
			foreach($var as $k=>$v) {
				$ret[$k] = mysql_real_escape_string($v);
			}
		} else {
			$ret = mysql_real_escape_string($var);
		}
		return $ret;
	}
	
	public function query($sql,$debug=0,$unsafe=0)
	{
		if($unsafe == 0) {
			$sql = self::prepare($sql);
		}	
		if($debug == 1) {
			print "$sql <br />";
		}
		$data = array();
		if($q = mysql_query($sql)) {
			$ins_id = @self::getInsertId();
			if(!empty($ins_id) || ($ins_id != '')) {
				return $ins_id;
			} else {
				$rows = @self::numRows($q);
				if($rows == 1) {
					return @mysql_fetch_assoc($q);
				} else {
					while($row = @mysql_fetch_assoc($q)) {
						$data[] = $row;
					
					}
				}
				return $data;
			}
		}
		return false;
	}
	
	public function getInsertId()
	{
		return mysql_insert_id();
	}
	
	public function getError()
	{
		return mysql_error();
	}
	
	public function numRows($id)
	{
		return mysql_num_rows($id);
	}

	public function countFieldsInTable($name)
	{
		$q = self::query("SELECT * FROM `$name`");
		return count($q);
	}

}

class IPManager {

	public function __construct()
	{
		//$db = new DB;
		//$this->error = new Error;
	}
	
	/**
	 * Logs provided data provided by user or function, according to defined constant for "DEBUG_DEFAULT"
	 * 
	 * @param type: string
	 * @param message: string
	 * @param debug: bool
	 * @return bool
	 */
	function log($type,$message,$debug = DEBUG_DEFAULT)
	{
		if($debug == 1) {
			log_message($type,$message);
		}
		return;
	}
	
	/**
	 * Checks validity of provided IPv4 or IPv6 address.
	 * 
	 * @param string -> IP address to be validated
	 * @return bool -> Tells calling script whether or not IP is valid.
	 */
	public function isValidIP($ip)
	{
		$ip = self::expandIP($ip);
		if(filter_var($ip,FILTER_VALIDATE_IP)) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * @param string $ip
	 * @return boolean
	 * 
	 * Detects whether or not the IP provided is a valid IP to be the first in an allocation.
	 * i.e. 192.168.1.11/28 is not a valid IP block, as it starts on an odd number not divisible by 8
	 */
	public function isValidStartIP($ip)
	{
		$list = self::ipToArray($ip);
		$type = self::getIPType($ip);
		switch($type) {
			case 4:
				if($list[3] == 0) {
					return true;
				}
				if($list[3] == 255) {
					return false;
				}
				if((($list[3]) % 8) == 0) {
					return true;
				} else {
					return false;
				}
				break;
			case 6:
				if(hexdec($list[7]) == 0) {
					return true;
				}
				if(hexdec($list[7]) == 65535) {
					return false;
				}
				if((hexdec($list[7]) % 8) == 0) {
					return true;
				} else {
					return false;
				}
				break;
			default:
				return false;
				break;
		}
	}
	
	/**
	 * @param string -> IPv4 or IPv6 address whose type will be returned (IPv4/IPv6)
	 * @return int -> Protocol of IP address provided
	 * 
	 * Function getIPType returns the protocol of the given IP address, after checking validity of IP.
	 */
	final public function getIPType($ip)
	{
		//print "<h1>getIPType() -- IP = $ip</h1>";
		if(strpos($ip,':') && !strpos($ip,'.')) {
			return 6;
		} else if(strpos($ip,'.') && !strpos($ip,':')) {
			return 4;
		} else {
			return false;// Invalid ip, break process.
		}
	}
	
	final public function getIPTypeFromLong($ip)
	{
		if(strlen($ip) == 40) {
			return 6;
		} else if(strlen($ip) != 40) {
			return 4;
		}
	}
	
	/***
	 * ************* BORROWED FUNCTIONS FROM FORMER CLASS.IPMANAGER.PHP ***************
	 */
	/***
	 * Replaces broken ip2long() function of PHP.
	 * 
	 * NOTE: Fixed error in original logic of function.
	 * ERROR: 
	 * 
	 * @param string $a
	 * @return int
	 */
	function inet_aton($a) {
	    $inet = 0.0;
	    if (count($t = explode(".", $a)) != 4) {
	    	return false;
	    }
	    for ($i = 0; $i < 4; $i++) {
	        $inet *= 256.0;
	        $inet += $t[$i];
	    };
	    return $inet;
	}
	
	/***
	 * Replaces broken long2ip() function of PHP.
	 * 
	 * @param int $n
	 * @return string
	 */
	function inet_ntoa($n) {
	    $t=array(0,0,0,0);
	    $msk = 16777216.0;
	    $n += 0.0;
	    if ($n < 1)
	        return('0.0.0.0');
	    for ($i = 0; $i < 4; $i++) {
	        $k = (int) ($n / $msk);
	        $n -= $msk * $k;
	        $t[$i]= $k;
	        $msk /=256.0;
	    };
	    $a=join('.', $t);
	    return($a);
	}
	
	/***
	 * @param float $ip
	 * @return int
	 * 
	 * Function ipToLong is a more secure, accurate form of ip2long(), a core PHP function.
	 */
	public function ipToLong($ip)
	{
		if(!self::isValidIP($ip)) {
			return false;
		}
		$ip = self::expandIP($ip);
		$type = self::getIPType($ip);
		switch($type) {
			case 4:
				return self::inet_aton($ip);
				break;
			case 6:
				$list = self::ipToArray($ip);
				foreach($list as $k=>$v) {
					$list[$k] = hexdec($v);
					$list[$k] = str_pad($list[$k],5,0,STR_PAD_LEFT);
				}
				return implode('',$list);
				break;
			default:
				return false; // Invalid IP, would be caught long before this.
				break;
		}
		return false;
	}
	
	public function longToIP($ip)
	{	
		$type = self::getIPTypeFromLong($ip);
		switch($type) {
			case 4:
				return self::inet_ntoa($ip);
				break;
			case 6:
				$list = str_split($ip,5);
				foreach($list as $k=>$v) {
					$list[$k] = dechex($v);
				}
				return self::shrinkIP(implode(':',$list));
				break;
			default:
				return false; // Invalid IP, would be caught long before this.
		}
		return false;
	}
	
	/***
	 * @param string $ip
	 * @return string
	 * 
	 * Function expandIP ensures that all IP addresses fed to it are properly identified and adhere to a
	 * standardized format.
	 */
	public function expandIP($ip,$show=null)
	{
		$ip = strtolower($ip);
		$type = self::getIPType($ip);
		if($type==6) {
			// Check for abbreviated IPv6 address, such as 4a2b::1234:5678
			if(strpos($ip,"::")) {
				if(substr_count($ip,'::') > 1) {
					// throw error, invalid IP
					error::log('Invalid IP: More than 1 abbreviated block.',__FUNCTION__,__LINE__);
					return false;
				} else {
					$ip = self::unfoldIPv6($ip);
				}
			}
		}
		$list = self::ipToArray($ip,true);
		switch(self::getIPType($ip)) {
			case 4:
				$part_length = 3;
				break;
			case 6:
				$part_length = 4;
				break;
		}
		if($list == '') {
			return false;
		}
		foreach($list as $k=>$v) {
			if(strlen($v) < $part_length) {
				// Adds zeroes to the left of number (192.1.x.x becomes 192.001.x.x)
				$list[$k] = str_pad($v,$part_length,"0",STR_PAD_LEFT);
			}
		}
		$final = self::arrayToIP($list,$type);
		if($show == 1) {
			print $final;
		}
		return $final;
	}
	
	/***
	 * Take an expanded IP (1234:0000:0000:0000:0000:0000:0000:0001) and shrink to normal size (1234::1)
	 */
	public function shrinkIP($ip)
	{
		$type = self::getIPType($ip);
		$list = self::ipToArray($ip);
		if($list == '') {
			return false;
		}
		if($type == 4) {
			foreach($list as $k=>$v) {
				$list[$k] = ltrim($v,0);
				if($list[$k] == '') {
					$list[$k] = 0;
				}
			}
			$ip = self::arrayToIP($list,$type);
		}
		if($type == 6) {
			foreach($list as $k=>$v) {
				$list[$k] = ltrim($v,0);
				if(strlen($list[$k]) == 0) {
					$list[$k] = '0';
				}
			}
			foreach($list as $k=>$v) {
				if(($list[$k] == '0') && (!isset($start))) {
					$start = $k;
				}
				if($k < (count($list) - 1)) {
					if(($list[$k] === '0') && ($list[$k + 1] === '0')) {
						$end = $k + 1;
					} else if(($list[$k] == '0') && ($list[$k + 1] != '0')) {
						$end = $k;
					}
					if(($k == (count($list) - 2)) && ($list[$k] == '0') && ($list[$k + 1] == '0')) {
						$end = $k + 1;
						//break;
					}
				}
			}
			$list[$start] = '';
			for($i = $end;$i>$start;$i--) {
				unset($list[$i]);
			}
			if(array_key_exists((count($list) - 1),$list)) {
				if($list[count($list) - 1] == '') {
					$list[count($list)] = '';
				}
			}
			if(($type == 4) && ($list[count($list) - 1] == '')) {
				$list[count($list) - 1] == 0;
			}
			$ip = self::arrayToIP($list,$type);
			if(($ip[strlen($ip) - 1] == ':') && ($ip[strlen($ip) - 2] != ':')) {
				$ip = substr_replace($ip,'',-1);
			}
		}
		return $ip;
	}
	
	/***
	 * Take an abbreviated IPv6 address (i.e. 1234::5678) and fill all necessary zeroes.
	 */
	function unfoldIPv6($ip) {
		$count = substr_count($ip,":");
		if($ip[strlen($ip) - 1] == ':') {
			$count = $count - 1;
		}
		if(($count == 2) && (strlen($ip) == 6)) {
			$count = 1;
		}
		$pads = '';
		for($i=$count;$i<8;$i++) {
			$pads .= ':0000';
		}
		if($pads && ($ip[strlen($ip) - 1] != ':')) {
			$pads .= ':';
		}
		return str_replace("::",$pads,$ip);
	}	
	
	/***
	 * Create an enumerated array from a valid IP
	 */
	public function ipToArray($ip,$disable_checks=false)
	{
		if($disable_checks == false) {
			$ip = self::expandIP($ip);
			if(!self::isValidIP($ip)) {
				error::log('Invalid IP.',__FUNCTION__,__LINE__);
				return false;
			}
		}
		$type = self::getIPType($ip);
		switch($type) {
			case 4:
				$separator = '.';
				$part_length = 3;
				break;
			case 6:
				$separator = ':';
				$part_length = 4;
				break;
			// No default, as anything else is invalid.
		}
		return @explode($separator,$ip);
	}
	
	/***
	 * Create an valid IP from an enumerated array
	 */
	public function arrayToIP($ip,$type)
	{
		switch($type) {
			case 4:
				$separator = '.';
				break;
			case 6:
				$separator = ':';
				break;
			// No default, as anything else is invalid.
		}
		return @implode($separator,$ip);
	}
	
	/***
	 * Build array of all IPv4 CIDR notations (/32,/16), with corresponding numbers of IP addresses
	 */
	public function buildIPv4CIDRArray()
	{
		$cidr = array();
		$cidr['/32'] = 1;
		$inc = 31;
		for($i = 1;$inc>=0;$i++) {
			$cidr['/' . $inc] = pow(2,$i);
			$inc--;
		}
		return $cidr;
	}
	
	/***
	 * Build array of all IPv6 CIDR notations (/128,/64), with corresponding numbers of IP addresses
	 */
	public function buildIPv6CIDRArray()
	{
		$cidr = array();
		$cidr['/128'] = 1;
		$inc = 127;
		for($i = 1;$inc>=0;$i++) {
			$cidr['/' . $inc] = pow(2,$i);
			$inc--;
		}
		return $cidr;
	}
	
	/***
	 * Returns an integer amount of the IPs provided to function (/29 returns 8, etc.)
	 * NOTE: This function only accepts a CIDR notation (ex. /29 IPv4 or /112 IPv6)
	 */
	public function countIPsInBlock($size,$type)
	{
		if($type == 4) {
			$cidr = self::buildIPv4CIDRArray();
		} else if($type == 6) {
			$cidr = self::buildIPv6CIDRArray();
		}
		return $cidr[$size];
	}

	
	/***
	 * Returns the last IP in a given block. 
	 * 
	 * @param string $ip
	 * @param string $size
	 * @return string
	 */
	public function findEndOfIPBlock($start_ip,$size)
	{
		$type = self::getIPType($start_ip);
		$cidr = $size;
		$size = self::countIPsInBlock($size,$type);
		if(!self::isValidIP($start_ip)) {
			error::log("Invalid IP $start_ip.",__FUNCTION__,__LINE__);
			return false;
		}
		if(!self::isValidStartIP($start_ip)) {
			return false;
		}
		//print self::expandIP($start_ip) . '<br />';
		try {
			$message = "Invalid allocation size for starting IP '$start_ip$cidr'. No cross-block allocation is allowed.";
			switch($type) {
				case 4:
					$ip = @self::ipToArray($start_ip);
					$cidr = self::buildIPv4CIDRArray();
					if((($size >= $cidr['/24']) &&($size <= $cidr['/16']) && (($ip[3] > 0)))) {
						throw new exception($message);
					}
					if((($size > $cidr['/16']) &&($size <= $cidr['/8']) && (($ip[3] > 0) || ($ip[2] > 0)))) {
						throw new exception($message);
					}
					if($size > $cidr['/8']) {
						throw new exception("IP Block exceeds maximum allowed IP allocation.");
					}
					if($size == $cidr['/24']) {
						$ip[3] = 255;
					}
					if($size == $cidr['/16']) {
						$ip[2] = $ip[3] = 255;
					}
					if($size == $cidr['/8']) {
						$ip[1] = $ip[2] = $ip[3] = 255;
					}
					$start = self::inet_aton($start_ip);
					return self::inet_ntoa((self::inet_aton($start_ip) + $size) - 1);
					break;
				case 6:
					$ip = @self::ipToArray($start_ip);
					$cidr = self::buildIPv6CIDRArray();
					foreach($ip as $k=>$v) {
						$ip[$k] = hexdec($v);
					}
					if((($size >= $cidr['/112']) && ($size <= $cidr['/96'])) && (($ip[7] > 0))) {
						throw new exception($message);
					}
					if((($size > $cidr['/96']) && ($size <= $cidr['/80'])) && (($ip[6] > 0) || ($ip[7]) > 0)) {
						throw new exception($message);
					}
					if((($size > $cidr['/80']) && ($size <= $cidr['/64'])) && (($ip[5] > 0) || ($ip[6] > 0) || ($ip[7]) > 0)) {
						throw new exception($message);
					}
					if((($size > $cidr['/64']) && ($size <= $cidr['/48'])) && (($ip[4] > 0) || ($ip[5] > 0) || ($ip[6] > 0) || ($ip[7]) > 0)) {
						throw new exception($message);
					}
					if((($size > $cidr['/48']) && ($size <= $cidr['/32'])) && (($ip[3] > 0) || ($ip[4] > 0) || ($ip[5] > 0) || ($ip[6] > 0) || ($ip[7]) > 0)) {
						throw new exception($message);
					}
					if((($size > $cidr['/32']) && ($size <= $cidr['/16'])) && (($ip[2] > 0) || ($ip[3] > 0) || ($ip[4] > 0) || ($ip[5] > 0) || ($ip[6] > 0) || ($ip[7]) > 0)) {
						throw new exception($message);
					}
					if((($size > $cidr['/16']) && $size <= $cidr['/0'])) {
						throw new exception("IP block exceeds maximum available IP block allocation.");
					}
					if($size == $cidr['/96']) {
						$ip[6] = $ip[7] = 65535;
					}
					if($size == $cidr['/80']) {
						$ip[5] = $ip[6] = $ip[7] = 65535;
					}
					if($size == $cidr['/64']) {
						$ip[4] = $ip[5] = $ip[6] = $ip[7] = 65535;
					}
					if($size == $cidr['/48']) {
						$ip[3] = $ip[4] = $ip[5] = $ip[6] = $ip[7] = 65535;
					}
					if($size == $cidr['/32']) {
						$ip[2] = $ip[3] = $ip[4] = $ip[5] = $ip[6] = $ip[7] = 65535;
					}
					if($size == $cidr['/16']) {
						$ip[1] = $ip[2] = $ip[3] = $ip[4] = $ip[5] = $ip[6] = $ip[7] = 65535;
					}
					if($size <= $cidr['/112']) {
						$ip[7] = ($ip[7] + $size) - 1;
					} else if(($size > $cidr['/112']) && ($size <= $cidr['/96'])) {
						$rem =($size / $cidr['/112']) - 1;
						$ip[7] = 65535;
						$ip[6] = $rem;
					} else if(($size > $cidr['/96']) && ($size <= $cidr['/80'])) {
						$rem =($size / $cidr['/96']) - 1;
						$ip[6] = $ip[7] = 65535;
						$ip[5] = $rem;
					} else if(($size > $cidr['/80']) && ($size <= $cidr['/64'])) {
						$rem =($size / $cidr['/80']) - 1;
						$ip[5] = $ip[6] = $ip[7] = 65535;
						$ip[4] = $rem;
					} else if(($size > $cidr['/64']) && ($size <= $cidr['/48'])) {
						$rem =($size / $cidr['/64']) - 1;
						$ip[4] = $ip[5] = $ip[6] = $ip[7] = 65535;
						$ip[3] = $rem;
					} else if(($size > $cidr['/48']) && ($size <= $cidr['/32'])) {
						$rem =($size / $cidr['/48']) - 1;
						$ip[3] = $ip[4] = $ip[5] = $ip[6] = $ip[7] = 65535;
						$ip[2] = $rem;
					} else if(($size > $cidr['/32']) && ($size <= $cidr['/16'])) {
						$rem =($size / $cidr['/32']) - 1;
						$ip[2] = $ip[3] = $ip[4] = $ip[5] = $ip[6] = $ip[7] = 65535;
						$ip[1] = $rem;
					}					
					break;
				default:
					throw new exception("Invalid IP or allocation size provided.");
					break;
			}
		} catch (exception $e) {
			error::log($e->getMessage(),__FUNCTION__,__LINE__);
			return false;
		}
		foreach($ip as $k=>$v) {
			$ip[$k] = dechex($v);
		}
		return self::expandIP(self::arrayToIP($ip,$type));
	}
	
	/***
	 * Function to increment an IP by one. Used primarily in conjunction with findBestIPBlock()
	 * 
	 * @param string $ip
	 * @return string
	 */
	public function findNextIP($ip)
	{
		$type = self::getIPType($ip);
		$parts = self::ipToArray($ip);
		$ip = array();
		try {
			switch($type) {
				case 4:
				$ip = array();
				foreach($parts as $k=>$v) {
					$ip[$k] = $v;
				}
					if($ip[3] == 255) {
						if($ip[2] == 255) {
							if($ip[1] == 255) {
								if($ip[0] == 255) {
									throw new exception("Invalid IP. Unable to increment by 1.");
								} else {
									++$ip[0];
									$ip[3] = $ip[2] =$ip[1] = 0;	
								}
							} else {
								++$ip[1];
								$ip[3] = $ip[2] = 0;
							}
						} else {
							++$ip[2];
							$ip[3] = 0;
						}
					} else {
						++$ip[3];
					}
					break;
				case 6:
					foreach($parts as $k=>$v) {
						$ip[$k] = hexdec($v);
					}
					$k = 7;
					if($ip[$k] == 65535) {
						if($ip[$k - 1] == 65535) {
							if($ip[$k - 2] == 65535) {
								if($ip[$k - 3] == 65535) {
									if($ip[$k - 4] == 65535) {
										if($ip[$k - 5] == 65535) {
											if($ip[$k - 6] == 65535) {
												if($ip[$k - 7] == 65535) {
													throw new exception("Invalid IP " . self::arrayToIP($ip,$type) . " unable to be incremented.");
												} else {
													++$ip[$k - 7];
													$ip[$k] = $ip[$k - 1] = $ip[$k - 2] = $ip[$k - 3] = $ip[$k - 4] = $ip[$k - 5] = $ip[$k - 6] = 0;
												}
											} else {
												++$ip[$k - 6];
												$ip[$k] = $ip[$k - 1] = $ip[$k - 2] = $ip[$k - 3] = $ip[$k - 4] = $ip[$k - 5] = 0;
											}
										} else {
											++$ip[$k - 5];
											$ip[$k] = $ip[$k - 1] = $ip[$k - 2] = $ip[$k - 3] = $ip[$k - 4] = 0;
										}
									} else {
										++$ip[$k - 4];
										$ip[$k] = $ip[$k - 1] = $ip[$k - 2] = $ip[$k - 3] = 0;
									}
								} else {
									++$ip[$k - 3];
									$ip[$k] = $ip[$k - 1] = $ip[$k - 2] = 0;
								}
							} else {
								++$ip[$k - 2];
								$ip[$k] = $ip[$k - 1] = 0;
							}
						} else {
							++$ip[$k - 1];
							$ip[$k] = 0;
						}
					} else {
						++$ip[$k];
					}
					foreach($ip as $k=>$v) {
						$ip[$k] = dechex($v);
					}
					break;
			}
			$ip = self::arrayToIP($ip,$type);
			return $ip;
		} catch(exception $e) {
			error::log($e->getMessage(),__FUNCTION__,__LINE__);
			return false;
		}
	}
	
	/***
	 * Detects whether a given IP is within an IP block owned by the network.
	 * 
	 * @param string $ip
	 * @return boolean
	 */
	public function isWithinOwnedIPBlock($ip)
	{
		$type = self::getIPType($ip);
		$ip = self::ipToLong($ip);
		try {
			$owned_blocks = self::getAllIPBlocks();
			if(isset($owned_blocks[0])) {
				foreach($owned_blocks as $b) {
					if(($ip >= $b['starting_ip'])) {
						if(($ip <= $b['ending_ip'])) {
							return true;
						}
					} 
				}
			} else {
				if(($ip >= $owned_blocks['starting_ip'])) {
					if(($ip <= $owned_blocks['ending_ip'])) {
						return true;
					}
				} 
			}
			throw new exception("IP '$ip' is not within any owned ranges.");
		} catch(exception $e) {
			error::log($e->getMessage(),__FUNCTION__,__LINE__);
			return false;
		}
		return false;
	}
	
	/***
	 * Checks all system IP blocks to see if a new incoming IP block is taken already or will cause overlap.
	 * 
	 * @param string $start_ip,$end_ip
	 * @param int $type
	 * @return boolean
	 */
	public function ipBlockFree($start_ip,$end_ip,$type)
	{
		list($start_ip,$end_ip) = db::prepare(func_get_args());
		try {
			$blocks = self::getAllIPBlocks();
			if(isset($blocks[0])) {
				foreach($blocks as $b) {
					if($b['ip_type'] == $type) {
						if((($start_ip >= $b['starting_ip']) && ($start_ip <= $b['ending_ip'])) || (($end_ip <= $b['ending_ip']) && ($end_ip >= $b['starting_ip']))) {
							throw new exception("IP Block starting with IP '" . self::longToIP($start_ip) . "' already exists.");
						}
					}
				}
			} else {
				if($blocks['ip_type'] == $type) {
					if((($start_ip >= $blocks['starting_ip']) && ($start_ip <= $blocks['ending_ip'])) || (($end_ip <= $blocks['ending_ip']) && ($end_ip >= $blocks['starting_ip']))) {
						throw new exception("IP Block starting with IP '" . self::longToIP($start_ip) . "' already exists.");
					}
				}
			}
			return true;
		} catch(exception $e) {
			error::log($e->getMessage(),__FUNCTION__,__LINE__);
			return false;
		}
		return false;
	}
	
	/***
	 * Checks all allocated IP blocks to see if a new incoming IP block is taken already or will cause overlap.
	 * 
	 * @param string $start_ip,$end_ip
	 * @param int $type
	 * @return boolean
	 */
	public function ipAllocationFree($start_ip,$end_ip,$type)
	{
		list($start_ip,$end_ip,$type) = db::prepare(func_get_args());
		try {
			$start_ip = self::ipToLong($start_ip);
			$end_ip = self::ipToLong($end_ip);
			//print "<h1>ipAllocationFree --- Type: $type, Start: $start_ip, End: $end_ip</h1>";
			$allocations = self::getAllAssignedIPRanges($type);
			if(isset($allocations[0])) {
				foreach($allocations as $a) {
					if($a['ip_type'] == $type) {
						if(bccomp($start_ip,$a['starting_ip']) >= 0) {
							if(bccomp($start_ip,$a['ending_ip']) == -1) {
								return false;
							}
						}
						if(bccomp($end_ip,$a['starting_ip']) >= 0) {
							if(bccomp($end_ip,$a['ending_ip']) <= 0) {
								return false;
							}
						}
					}
				}
			} else {
				if($allocations['ip_type'] == $type) {
					if(bccomp($start_ip,$allocations['starting_ip']) >= 0) {
						if(bccomp($start_ip,$allocations['ending_ip']) == -1) {
							return false;
						}
					}
					if(bccomp($end_ip,$allocations['starting_ip']) == 1) {
						if(bccomp($end_ip,$allocations['ending_ip']) <= 0) {
							return false;
						}
					}
				}
			}
			return true;
		} catch(exception $e) {
			error::log($e->getMessage(),__FUNCTION__,__LINE__);
			return false;
		}
		return false;
	}
	
	function isTrue() 
	{
		return true;
	}

	/***
	 * Calculate whether the desired IP is already taken, or in another allocated block
	 * 
	 * @param string $start_ip,$size
	 * @return boolean
	 */
	public function isAllocatable($start_ip,$size)
	{	
		list($start_ip,$size) = db::prepare(func_get_args());
		$type = self::getIPType($start_ip);
		$start_ip = self::expandIP($start_ip);
		try {
			if(!self::isValidIP($start_ip)) {
				throw new exception('Invalid IP.');
			}
			if(!self::isValidStartIP($start_ip)) {
				throw new exception("Invalid starting IP '$start_ip' provided.");
			}
			if(!self::isWithinOwnedIPBlock($start_ip)) {
				throw new exception("IP block " . $start_ip . " is not within an IP block under system control.");
			}
			if(!$end = self::findEndOfIPBlock($start_ip,$size)) {
				throw new exception("IP block is invalid, and no ending IP address could be found.");
			}
			if(!self::ipAllocationFree($start_ip,self::findEndOfIPBlock($start_ip,$size),$type)) {
				throw new exception("IPv$type block '$start_ip$size' is not free to be allocated.");
			}
			return true;
		} catch(exception $e) {
			//error::log($e->getMessage(),__FUNCTION__,__LINE__);
			return false;
		}
		return false;
	}
	
	/***
	 * When finding an IP block, locate an IP block that sits squarely on top of another IP block,
	 * instead of having them spread out widely across the IP range.
	 * 
	 * @param string $size
	 * @param int $type
	 * @return string
	 */
	public function findBestIPBlock($size,$type)
	{
		list($size,$type) = db::prepare(func_get_args());
		try {
			if(!isset($size) || !isset($type)) {
				throw new exception("Parameters for size and type of IP block required.");
			}
			$allocations = self::getAllAssignedIPRanges($type);
			$blocks = self::getAllIPBlocks($type);
			if(isset($allocations[0])) {
				foreach($allocations as $a) {
					if($a['ip_type'] == $type) {
						$st = self::longToIP($a['starting_ip']);
						$en = self::longToIP($a['ending_ip']);
						$new_st = self::findNextIP($en);
						if(!$new_en = self::findEndOfIPBlock($new_st,$size)) {
							$new_st = self::findAvailableIPBlock($type,$size);
							if(!$new_en = self::findEndOfIPBlock($new_st,$size)) {
								$new_st = self::incrementBlock($new_st,$size);
								if(!$new_en = self::findEndOfIPBlock($new_st,$size)) {
									throw new exception("IP allocation manager unable to find a suitable IP address for allocation.");
								}
							}
						}
						if(self::ipAllocationFree($new_st,$new_en,$type)) {
							print "<h1>Jackpot! $new_st$size</h1>";
							return self::shrinkIP($new_st);
						}
					}
				}
			} else {
				if($allocations['ip_type'] == $type) {
					$st = self::longToIP($allocations['starting_ip']);
					$en = self::longToIP($allocations['ending_ip']);
					$new_st = self::findNextIP($en);
					if(!$new_en = self::findEndOfIPBlock($new_st,$size)) {
						$new_st = self::findAvailableIPBlock($type,$size);
						if(!$new_en = self::findEndOfIPBlock($new_st,$size)) {
							$new_st = self::incrementBlock($new_st,$size);
							if(!$new_en = self::findEndOfIPBlock($new_st,$size)) {
								throw new exception("IP allocation manager unable to find a suitable IP address for allocation.");
							}
						}
					}
					//print "<h1>Start: $st, End: $en, NextIP: $new_st, NextEnd: $new_en</h1>";
					if(self::ipAllocationFree($new_st,$new_en,$type)) {
						print "<h1>Jackpot! $new_st$size</h1>";
						return self::shrinkIP($new_st);
					}
				}
			}
			// No blocks available at the end of existing IP allocations, check into other blocks now.
			if(isset($blocks[0])) {
				foreach($blocks as $b) {
					if($blocks['ip_type'] == $type) {
						$start = self::longToIP($b['starting_ip']);
						if(self::ipAllocationFree($start,self::findEndOfIPBlock($start,$size),$type)) {
							return self::shrinkIP($start);
						}
					}
				}
			} else {
				if($blocks['ip_type'] == $type) {
					$start = self::longToIP($blocks['starting_ip']);
					if(self::ipAllocationFree($start,self::findEndOfIPBlock($start,$size),$type)) {
						return self::shrinkIP($start);
					}
				}
			}
			// No IPs available so far, let's do this the hard way.
			if($q = self::findAvailableIPBlock($type,$size)) {
				return $q;
			}
			// No IPs available to allocate. Get some more IPs! :P
			throw new exception("Insufficient space to allocate $size IP block.");
		} catch(exception $e) {
			error::log($e->getMessage(),__FUNCTION__,__LINE__);
			return false;
		}
	}
	
	public function findAvailableIPBlock($type,$size)
	{
		$blocks = self::getAllIPBlocks($type);
		$alls = self::getAllAssignedIPRanges($type);
		$block_size = self::countIPsInBlock($size,$type);
		try {
			if(isset($blocks[0])) {
				foreach($blocks as $b) {
					$start = $blocks['hr_start'];
					for($i=0;$i<50;$i++) {
						if((strlen($start) <= 7) || ($type == '')) {
							throw new exception("No available IP block located.");
						}
						$end = self::findEndOfIPBlock($start,$size);
						if(!self::ipAllocationFree($start,$end,$type)) {
							$start = self::incrementBlock($start,$size);
						} else {
							return $start;
						}
					}
				}
			} else {
				$start = $blocks['hr_start'];
				for($i=0;$i<50;$i++) {
					if((strlen($start) <= 7) || ($type == '')) {
						throw new exception("No available IP block located.");
					}
					$end = self::findEndOfIPBlock($start,$size);
					if(!self::ipAllocationFree($start,$end,$type)) {
						$start = self::incrementBlock($start,$size);
					} else {
						return $start;
					}
				}
			}
		} catch(exception $e) {
			error::log($e->getMessage(),__FUNCTION__,__LINE__);
		}
	}
	
	public function incrementBlock($ip,$size)
	{
		$type = self::getIPType($ip);
		$ip = self::ipToArray($ip);
		$size = self::countIPsInBlock($size,$type);
		switch($type) {
			case 4:
				if(($size >= 256) && ($size < 65536)) {
					++$ip[2];
					$ip[3] = 0;
				}
				if(($size >= 65536) && ($size < 16777216)) {
					++$ip[1];
					$ip[2] = $ip[3] = 0;
				}
				if(($size >= 16777216) && ($size < 4294967296)) {
					++$ip[0];
					$ip[1] = $ip[2] = $ip[3] = 0;
				}
				break;
			case 6:
				foreach($ip as $k=>$v) {
					$ip[$k] = hexdec($v);
				}
				$cidr = self::buildIPv6CIDRArray();
				if(($size >= $cidr['/112']) && ($size < $cidr['/96'])) {
					++$ip[6];
					$ip[7] = 0;
				}
				if(($size >= $cidr['/96']) && ($size < $cidr['/80'])) {
					++$ip[5];
					$ip[6] = $ip[7] = 0;
				}
				if(($size >= $cidr['/80']) && ($size < $cidr['/64'])) {
					++$ip[4];
					$ip[5] = $ip[6] = $ip[7] = 0;
				}
				if(($size >= $cidr['/64']) && ($size < $cidr['/48'])) {
					++$ip[3];
					$ip[4] = $ip[5] = $ip[6] = $ip[7] = 0;
				}
				if(($size >= $cidr['/48']) && ($size < $cidr['/32'])) {
					++$ip[2];
					$ip[3] = $ip[4] = $ip[5] = $ip[6] = $ip[7] = 0;
				}
				if(($size >= $cidr['/32']) && ($size < $cidr['/16'])) {
					++$ip[1];
					$ip[2] = $ip[3] = $ip[4] = $ip[5] = $ip[6] = $ip[7] = 0;
				}
				if(($size >= $cidr['/16']) && ($size < $cidr['/0'])) {
					++$ip[0];
					$ip[1] = $ip[2] = $ip[3] = $ip[4] = $ip[5] = $ip[6] = $ip[7] = 0;
				}
				foreach($ip as $k=>$v) {
					$ip[$k] = dechex($v);
				}
		}
		return self::arrayToIP($ip,$type);
	}
	
	/***
	 *	Selects a new IP range and assigns it to a designated server. Optionally takes an IP address of preference.
	 *
	 *	@param int $type,$server
	 *	@param string $size,$ip
	 */
	public function assignIPRange($type,$size,$server,$ip=null)
	{
		list($type,$size,$server) = db::prepare(func_get_args());
		try {
			if($ip == null) {
				$ip = self::findBestIPBlock($size,$type);
			} else {
				if(!self::isAllocatable($ip,$size)) {
					throw new exception("IP '$ip' is not allocatable. IP range not assigned.");
				}
			}
			// Prepare vars
			$ip = self::expandIP($ip);
			print "<h1>$ip</h1>";
			$hr_end = self::findEndofIPBlock($ip,$size);
			$starting_ip = self::ipToLong($ip);
			$ending_ip = self::ipToLong($hr_end);
			$block_size = self::countIPsInBlock($size,$type);
			$now = date(CURRENT_DATE_FORMAT);
			$ip = self::shrinkIP($ip);
			$hr_end = self::shrinkIP($hr_end);
			// In future, checks will be inserted here to determine validity of server id
			if(($ip == '') || ($size == '')) {
				throw new exception("Missing arguments required for function to continue.");
			}
			// No errors, moving on.
			$sql = "INSERT INTO `allocations` (
						`allocation_size`,
						`starting_ip`,
						`ending_ip`,
						`hr_start`,
						`hr_end`,
						`ip_type`,
						`server_id`,
						`created_on`
					) VALUES (
						'$block_size',
						'$starting_ip',
						'$ending_ip',
						'$ip',
						'$hr_end',
						'$type',
						'$server',
						'$now'
					)";
			if(!$q = db::query($sql,1,1)) {
				throw new exception("Unable to assign new IP block to server. " . db::getError());
			} else {
				error::log("IPv$type block '$ip$size' was successfully created and assigned to server $server.");
				return db::getInsertId();
			}
		} catch(exception $e) {
			error::log($e->getMessage(),__FUNCTION__,__LINE__);
		}
		return false;
	}
	
	public function getAssignedIPRanges($server)
	{
		$server = db::prepare($server);
		try {
			if(strcmp($server,'') == 0) {
				throw new exception("Server ID is missing. Unable to proceed.");
			}
			if(!is_numeric($server)) {
				throw new exception("Server ID invalid. Must be numeric.");
			}
			$sql = "SELECT * FROM `allocations` WHERE `server_id` = $server";
			if(!$q = db::query($sql,1,1)) {
				throw new exception('Server IP allocation lookup failed: ' . db::getError());
			} else {
				return $q;
			}
		} catch(exception $e) {
			error::log($e->getMessage(),__FUNCTION__,__LINE__);
			return false;
		}
		
	}
	
	/***
	 * Retrieve complete array of IP assignments in database.
	 */
	public function getAllAssignedIPRanges($type=null)
	{
		$sql = "SELECT * FROM `allocations` ";
		if($type != null) {
			$type = db::prepare($type);
			$sql .= "WHERE `ip_type` = '$type' ";
		}
		$sql .= "ORDER BY `allocation_id` DESC";
		return db::query($sql,null,1);
	}
	
	/***
	 * Retrieve complete array of IP blocks currently owned by network.
	 */
	public function getAllIPBlocks($type=null)
	{
		$sql = "SELECT * FROM `ip_blocks` ";
		if($type != null) {
			$type = db::prepare($type);
			$sql .= "WHERE `ip_type` = '$type' ";
		}
		$sql .= "ORDER BY `block_id` DESC";
		return db::query($sql,null,1);
	}
	
	public function createNewIPBlock($start_ip,$size)
	{
		list($start_ip,$size) = db::prepare(func_get_args());
		$type = self::getIPType($start_ip);
		$ending_ip = self::ipToLong(self::findEndOfIPBlock($start_ip,$size));
		$start_ip = self::ipToLong(self::expandIP($start_ip));
		$hr_start = self::longToIP($start_ip);
		$hr_end = self::longToIP($ending_ip);
		try {
			if(!self::ipBlockFree($start_ip,$ending_ip,$type)) {
				throw new exception('IP block already exists in database. Unable to create block.');
			} else {
				if(($start_ip == '') || ($ending_ip == '') || ($type == '')) {
					throw new exception('One or more arguments is empty, and the query cannot be run.');
				}
				$num_size = self::countIPsInBlock($size,$type);	
				$date = date(CURRENT_DATE_FORMAT);
				$sql = "INSERT INTO `ip_blocks` 
							(	`starting_ip`,
								`ending_ip`,
								`hr_start`,
								`hr_end`,
								`ip_type`,
								`block_size`,
								`created_on`
							) VALUES
							(	'$start_ip',
								'$ending_ip',
								'$hr_start',
								'$hr_end',
								'$type',
								'$num_size',
								'$date'
							)";
				
				if(db::query($sql,null,1)) {
					error::log("New IPv$type block '$hr_start$size' created, range: $hr_start - $hr_end.");
					return true;
				} else {
					throw new Exception("IP block starting with IP $start_ip unable to be created.");
				}
			}
		} catch(Exception $e) {
			error::log($e->getMessage(),__FUNCTION__,__LINE__);
		}
		return false;
	}
	
	public function deleteIPBlock($start_ip,$type='block')
	{
		list($start_ip,$type) = db::prepare(func_get_args());
		$ip_type = self::getIPType($start_ip);
		$start_ip = self::ipToLong($start_ip);
		switch($type) {
			case 'block':
				$table = 'ip_blocks';
				break;
			case 'allocation':
				$table = 'allocations';
				break;
			default:
				$table = 'ip_blocks';
				break;
		}
		$sql = "DELETE FROM $table WHERE `starting_ip` = '$start_ip'";
		try {
			if(!$q = db::query($sql,1,1)) {
				throw new exception("Unable to delete IPv$ip_type block '" . self::longToIP($start_ip) . "'. MySQL reports: " . db::getError());
			} else {
				return true;
			}
		} catch(exception $e) {
			error::fullHalt($e->getMessage(),__FUNCTION__,__LINE__);
		}
		return false;
	}
}
/***
 * End of class.ipmanager.php
 */

//print '<pre>';
//error::log("This is a test log message.",null,__LINE__);
//error::fullHalt("This is a test full halt.");
//$ip = new IPManager;
//$db = new DB;
//$newipv6 = "2001:fb3d:127f:0::ffff:fff" . dechex(15);
//$ipv6 = "2001:abcd::25";
//$ipv4 = "216.245.0.24";
//print $ip->createNewIPBlock('2005:cdef::','/64');
//print $ip->assignIPRange(6,'/112','1234');
//print $ip->newDomain('simpletestdomain.com',null,null,'NATIVE',null,null,'SOA','ns.simpletestdomain.com',86400,null,date(CURRENT_DATE_FORMAT));

//print '</pre>';
?>