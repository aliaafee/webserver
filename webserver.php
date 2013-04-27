<?php
/*
    Webserver 3.0
*/

/*--Settings--------------------------------------------------------------------*/

$webServerSetting['listenIP'] = '127.0.0.1';
$webServerSetting['listenPort'] = '27016';
$webServerSetting['webRoot'] = getcwd().'/htdocs/';
$webServerSetting['documentRoot'] = 'index.html';
$webServerSetting['dateFormat'] = 'D, j M Y H:i:s';
$webServerSetting['serverIdentity'] = 'Webserver 3.0';
$webServerSetting['timezone'] = 'Asia/Katmandu';

/*------------------------------------------------------------------------------*/

date_default_timezone_set($webServerSetting['timezone']);

include 'httpserver.class.php';

class WebServer extends httpserver {
    private $_webRoot;
    private $_documentRoot;
    private $_dateFormat;
    
    public function __construct($listenIP,$listenPort,$webRoot,$documentRoot,$dateFormat,$serverIdentity) {
        $this->_listenIP = $listenIP;
        $this->_listenPort = $listenPort;
        $this->_webRoot = $webRoot;
        $this->_documentRoot = $documentRoot;
        $this->_dateFormat = $dateFormat;
        $this->_serverIdentity = $serverIdentity;
    }
    
    public function __destruct() {
        
    }
    
    private function parseFileName($fileName) {
        $fileNameP = array();
        $fArray = explode('#',$fileName);
        $fArray = explode('?',$fArray[0]);
        if (isset($fArray[1])) { $fileNameP['queryString'] = $fArray[1]; }
        $fileNameP['fileName'] = $fArray[0];
        return $fileNameP;
    }
    
    private function getDirectoryIndex($fileName) {
        echo "getting dir index";
        $fileName = rtrim($fileName,'/');
        if ($h = opendir($this->_webRoot.$fileName)) {
            $reply_body = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN"><html><head><title>Index of - '.$fileName.'/</title></head><body><h1>Index of - '.$fileName.'/</h1><hr><table width="640px;">';
            $href = explode('/',$fileName); array_pop($href);
            if (count($href) == 1 ) { $href='/'; } else { $href = implode('/',$href); }
            $reply_body .= '<tr><td colspan="3"><a href="'.$href.'">[Back]</a></td></tr>';
            while (($sub_file_name = readdir($h)) !== false) {
                $sub_file_modified = date($this->_dateFormat,filemtime($this->_webRoot.$fileName.'/'.$sub_file_name));
                if (substr($sub_file_name, 0, 1) != ".") {
                    if (is_dir($this->_webRoot.$fileName.'/'.$sub_file_name)) {
                        if ($sub_file_name != '.' && $sub_file_name != '..') {
                            $reply_body .= '<tr><td><a href="'.$fileName.'/'.$sub_file_name.'/">'.$sub_file_name.'</a></td><td>&lt;dir&gt;</td><td>'.$sub_file_modified.'</td></tr>';
                        }
                    } else {
                        $sub_file_size = filesize($this->_webRoot.$fileName.'/'.$sub_file_name);
                        $reply_body .= '<tr><td><a href="'.$fileName.'/'.$sub_file_name.'">'.$sub_file_name.'</a></td><td>'.$sub_file_size.'</td><td>'.$sub_file_modified.'</td></tr>';
                    }
                }
            }
            $reply_body .= '</table><hr><address>'.$this->_serverIdentity.' Server at '.$this->_listenIP.' Port '.$this->_listenPort.'</address></body></html>';
            return $reply_body;
        } else {
            return false;
        }
    }

    protected function httpRequestHandler($httpRequestP,$httpReplyDefault) {
        $httpReply = $httpReplyDefault;
    
        $fileNameP = $this->parseFileName($httpRequestP['fileName']);
        $tFileName = rtrim($fileNameP['fileName'],'/');
        $returnFileName = $this->_webRoot.$tFileName;
    
        if (file_exists($returnFileName)) {
            if (substr($fileNameP['fileName'],-1,1)=='/') {
                if (is_dir($returnFileName)) {
                    $returnDoumentIndex = "$returnFileName/".$this->_documentRoot;
                    if (file_exists($returnDoumentIndex)) {
                        $httpReply['mimeType'] = mime_content_type($returnDoumentIndex);
                        $httpReply['body'] = file_get_contents($returnDoumentIndex);
                    } else {
                        $httpReply['body'] = $this->getDirectoryIndex($fileNameP['fileName']);
                    }
                } else {
                    $httpReply['status'] = '404 Not Found';
                    $httpReply['body'] = $this->errorPage('404 Not Found','404 Not Found',
                                "The resource '{$fileNameP['fileName']}' was not found");
                }
            } else {
                if (is_dir($returnFileName)) {
                    $httpReply['status'] = '301 Moved Permanently';
                    $httpReply['body'] = 'moved permanently';
                    $httpReply['extra'] = "Location: {$tFileName}/\r\n";
                } else {
                    $httpReply['mimeType'] = mime_content_type($returnFileName);
                    $httpReply['body'] = file_get_contents($returnFileName);
                }
            }
        } else {
            $httpReply['status'] = '404 Not Found';
            $httpReply['body'] = $this->errorPage('404 Not Found','404 Not Found',
                                "The resource '{$fileNameP['fileName']}' was not found");
        }
        return $httpReply;
    }
}

function isValidIP($ip_addr){
    //function to validate ip address format in php by Roshan Bhattarai(http://roshanbh.com.np)
    //first of all the format of the ip address is matched
    if(preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/",$ip_addr)) {
        //now all the intger values are separated
        $parts=explode(".",$ip_addr);
        //now we need to check each part can range from 0-255
        foreach($parts as $ip_parts) {
            if(intval($ip_parts)>255 || intval($ip_parts)<0)
                return false; //if number is not within range of 0-255
            }
            return true;
        }
    else
    return false; //if format of ip address doesn't matches
}

function isValidPort($port){
    return is_numeric($port);
}

if (count($argv)==3) {
    if (isValidIP($argv[1])) {
         $webServerSetting['listenIP'] = $argv[1];
    }
    if (isValidPort($argv[2])) {
        $webServerSetting['listenPort'] = $argv[2];
    }
}

$webserver = new WebServer(
    $webServerSetting['listenIP'],
    $webServerSetting['listenPort'],
    $webServerSetting['webRoot'],
    $webServerSetting['documentRoot'],
    $webServerSetting['dateFormat'],
    $webServerSetting['serverIdentity']);

$webserver->start();

?>
