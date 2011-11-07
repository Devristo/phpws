<?php

class WebSocketProtocolVersions{
	const HIXIE_76 = 0;
	const HYBI_8 = 8;
	const HYBI_9 = 8;
	const HYBI_10 = 8;
	const HYBI_11 = 8;
	const HYBI_12 = 8;

	const LATEST = self::HYBI_12;

	private function __construct(){}
}

class WebSocketFunctions{
	/**
	 * Parse a HTTP HEADER 'Cookie:' value into a key-value pair array
	 *
	 * @param string $line Value of the COOKIE header
	 * @return array Key-value pair array
	 */
	public static function cookie_parse( $line ) {
		$cookies = array();
		$csplit = explode( ';', $line );
		$cdata = array();

		foreach( $csplit as $data ) {

			$cinfo = explode( '=', $data );
			$key = trim( $cinfo[0] );
			$val = urldecode($cinfo[1]);

			$cookies[$key] = $val;

		}

		return $cookies;
	}

	/**
	 * Parse HTTP request into an array
	 *
	 * @param string $header HTTP request as a string
	 * @return array Headers as a key-value pair array
	 */
	public static function parseHeaders( $header )
	{
		$retVal = array();
		$fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
		foreach( $fields as $field ) {
			if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
				$match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
				if( isset($retVal[$match[1]]) ) {
					$retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
				} else {
					$retVal[$match[1]] = trim($match[2]);
				}
			}
		}

		if(preg_match("/GET (.*) HTTP/" ,$header,$match)){
			$retVal['GET'] = $match[1];
		}

		return $retVal;
	}

	public static function calcHybiResponse($challenge){
		return base64_encode(sha1($challenge.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
	}

	/**
	 * Calculate the #76 draft key based on the 2 challenges from the client and the last 8 bytes of the request
	 *
	 * @param string $key1 Sec-WebSocket-Key1
	 * @param string $key2 Sec-Websocket-Key2
	 * @param string $l8b Last 8 bytes of the client's opening handshake
	 */
	public static function calcHixieResponse($key1,$key2,$l8b){
		// Get the numbers from the opening handshake
		$numbers1 = preg_replace("/[^0-9]/","", $key1);
		$numbers2 = preg_replace("/[^0-9]/","", $key2);

		//Count spaces
		$spaces1 = substr_count($key1, " ");
		$spaces2 = substr_count($key2, " ");

		if($spaces1 == 0 || $spaces2 == 0){
			throw new WebSocketInvalidKeyException($key1, $key2, $l8b);
			return null;
		}

		// Key is the number divided by the amount of spaces expressed as a big-endian 32 bit integer
		$key1_sec = pack("N", $numbers1 / $spaces1);
		$key2_sec = pack("N", $numbers2 / $spaces2);

		// The response is the md5-hash of the 2 keys and the last 8 bytes of the opening handshake, expressed as a binary string
		return md5($key1_sec.$key2_sec.$l8b,1);
	}

	public static function randHybiKey(){
		return base64_encode(
			chr(rand(0, 255)).chr(rand(0, 255)).chr(rand(0, 255)).chr(rand(0, 255))
			.chr(rand(0, 255)).chr(rand(0, 255)).chr(rand(0, 255)).chr(rand(0, 255))
			.chr(rand(0, 255)).chr(rand(0, 255)).chr(rand(0, 255)).chr(rand(0, 255))
			.chr(rand(0, 255)).chr(rand(0, 255)).chr(rand(0, 255)).chr(rand(0, 255))
		);
	}

	/**
	* Output a line to stdout
	*
	* @param string $msg Message to output to the STDOUT
	*/
	public static function say($msg = ""){
		echo date("Y-m-d H:i:s")." | ".$msg."\n";
	}
}