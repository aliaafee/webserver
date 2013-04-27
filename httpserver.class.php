<?php
/*
	class httpserver
	
	generic httpserver class
*/

class httpserver {
	protected $_listenIP;
	protected $_listenPort;
	protected $_listenSocket;
	protected $_clientIP;
	protected $_clientPort;
	protected $_comSocket;
	protected $_errorMessage;
	protected $_statusMessage;
	protected $_httpRequest;
	protected $_httpRequestP;
	protected $_httpReply;
	protected $_requestHandler;
	private $_serverIdentity;
	

	public function __construct($listenIP,$listenPort) {
		$this->_listenIP = $listenIP;
		$this->_listenPort = $listenPort;
		$this->_serverIdentity = "httpserver.class.php";
	}
	
	public function __destruct() {
		
	}
	
	protected function errorPage($title,$heading,$detail) {
		$html = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN"><html><head><title>%err_title%</title></head><body><h1>%err_heading%</h1><p>%err_detail%</p><hr><address>%err_server% Server at %err_host% Port %err_port%</address></body></html>';
		$html = str_replace('%err_title%',$title,$html);
		$html = str_replace('%err_heading%',$heading,$html);
		$html = str_replace('%err_detail%',$detail,$html);
		$html = str_replace('%err_server%',$this->_serverIdentity,$html);
		$html = str_replace('%err_host%',$this->_listenIP,$html);
		$html = str_replace('%err_port%',$this->_listenPort,$html);

		return $html;
	}
	
	private function logStatus($message) {
		$this->_statusMessage = $message;
		echo date('Ymd H:i:s')." | $message\r\n";
	}
	
	private function logError($message) {
		$this->_errorMessage = $message;
		echo date('Ymd H:i:s')." | Error: $message\r\n";
	}
	
	private function createListenSocket() {
		if ($this->_listenSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
			if (socket_bind($this->_listenSocket, $this->_listenIP, $this->_listenPort)) {
				if (socket_listen($this->_listenSocket,5)) {
					return true;
				} else {
					$this->_logError("Cannot Listen on Socket at on {$this->_listenIP}:{$this->_listenPort}");
				}
			} else {
				$this->logError("Cannot Bind To Socket at {$this->_listenIP}:{$this->_listenPort}");
			}
		} else { 
			$this->logError("Cannot Create Socket at {$this->_listenIP}:{$this->_listenPort}");
		}
		return false;
	}
	
	private function closeListenSocket() {
		socket_close($this->_listenSocket);
	}
	
	private function acceptConnection() {
		if ($this->_comSocket = socket_accept($this->_listenSocket)) {
			socket_getpeername($this->_comSocket,$this->_clientIP,$this->_clientPort);
			return true;
		}
		return false;
	}
	
	private function closeConnection() {
		socket_close($this->_comSocket);
	}
	
	private function parseHttpRequest() {
		$this->_httpRequestP = array();
		$array = explode("\r\n",trim($this->_httpRequest));
		//Parse Request Header
		foreach ($array as $key => $line) {
			if ($key == 0) {
				$line_array = explode(' ',$line);
				//Determine Request Type
				if (isset($line_array[2]) ? (($line_array[2] == 'HTTP/1.1') || ($line_array[2] == 'HTTP/1.0')) : FALSE) {
					$this->_httpRequestP['type'] = $line_array[2];
				} else {
					//Stop if the rquest is invalid
					return false;
				}
				
				$validMethods = array('GET','POST','OPTIONS');

				//Determine Request Method
				if (in_array($line_array[0],$validMethods)) {
					$this->_httpRequestP['method'] = $line_array[0];
				} else {
					return false;
				}

				//File Name
				$this->_httpRequestP['fileName'] = $line_array[1];
			} else {
				$line_array = explode(': ',$line);
				$this->_httpRequestP[trim($line_array[0])] = trim($line_array[1]);
			}
		}
		$this->_httpRequestP['raw'] = $this->_httpRequest;
		return true;
	}
	
	private function getHttpRequest(){
		$this->_httpRequest = '';
		while (substr($this->_httpRequest, -4, 4) != "\r\n\r\n") {
			if ($buffer = socket_read($this->_comSocket, 2048, PHP_NORMAL_READ)) {
				echo $buffer;
				$this->_httpRequest .= $buffer;
			} else {
				return false;
			}
		}
		return true;
	}
	
	private function getHttpRequestContent(){
		unset($this->_httpRequestP['Content']);
		if (isset($this->_httpRequestP['Content-Length'])) {
			if ($buffer = socket_read($this->_comSocket, $this->_httpRequestP['Content-Length'], PHP_BINARY_READ)) {
				$this->_httpRequestP['Content'] = $buffer;
				return true;
			}
		}
		return false;
	}
	
	private function processHttpRequest() {
		if ($this->parseHttpRequest()) {
			$this->getHttpRequestContent();
			$this->_httpReply['body'] = '<html><body><h1>Welcome</h1><p>This is the default reply</p></body></html>';
			$this->_httpReply['status'] = '200 OK';
			$this->_httpReply['mimeType'] = 'text/html';
			$this->_httpReply['extra'] = '';
			$this->_httpReply = $this->httpRequestHandler($this->_httpRequestP, $this->_httpReply);
			$this->_httpReply['length'] = strlen($this->_httpReply['body']);
		} else {
			$this->_httpReply['body'] = $this->errorPage('400 Bad Request','400 Bad Request',
									"<pre>$this->_httpRequest</pre>");
			$this->_httpReply['status'] = '400 Bad Request';
			$this->_httpReply['length'] = strlen($this->_httpReply['body']);
			$this->_httpReply['mimeType'] = 'text/html';
			$this->_httpReply['extra'] = '';
		}
		return true;
	}
	
	private function sendHttpReply() {
		$replyString = "HTTP/1.1 {$this->_httpReply['status']}\r\n".
				"Server: webserver2\r\n".
				"Date: ".date('D, j M Y H:i:s e')."\r\n".
				"Content-Length: {$this->_httpReply['length']}\r\n".
				"Content-type: {$this->_httpReply['mimeType']}\r\n".
				$this->_httpReply['extra'].
				"\r\n".
				$this->_httpReply['body'];
		if (socket_write($this->_comSocket, $replyString)) {
			return true;
		}
		return false;
	}
	
	private function mainLoop() {
		do {
			$this->logStatus("Listening on {$this->_listenIP}:{$this->_listenPort}");
			if ($this->acceptConnection()) {
				$this->logStatus("Connected to {$this->_clientIP}:{$this->_clientPort}");
				if ($this->getHttpRequest()) {
					$this->logStatus("Processing Request");
					$this->processHttpRequest();
					if ($this->sendHttpReply()) {
						$this->logStatus("Reply Sent");
					} else {
						$this->logError("Cannot Send Reply");
					}
				} else {
					$this->logError("Cannot Get Http Request");
				}
				$this->closeConnection();
			} else {
				$this->logError('Cannot Accept Connection');
			}
		} while(true);
	}
	
	protected function httpRequestHandler($httpRequestP,$httpReplyDefault) {
		$httpReply = $httpReplyDefault;
		$httpReply['body'] = $this->errorPage('httpserver.class.php','httpserver.class.php',
							"The Default Response");
		return $httpReply;
	}
	
	public function start() {
		if ($this->createListenSocket()) {
			$this->mainLoop();
			$this->closeListenSocket();
			return true;
		} else {
			$this->logError('Server Startup Failed');
			return false;
		}
	}
}

?>
