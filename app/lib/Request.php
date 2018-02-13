<?php
	class Request
	{
		var $_fp;
		var $_url;
		var $_host;
		var $_protocol;
		var $_uri;
		var $_port;

		private function _scan_url()
		{
			$req = $this->_url;
			$pos = strpos($req, '://');
			$this->_protocol = strtolower(substr($req, 0, $pos));
			$req = substr($req, $pos+3);
			$pos = strpos($req, '/');
			if($pos === false)
			{
				$pos = strlen($req);
			}
			$host = substr($req, 0, $pos);
			if(strpos($host, ':') !== false)
			{
				list($this->_host, $this->_port) = explode(':', $host);
			}
			else
			{
				$this->_host = $host;
				$this->_port = ($this->_protocol == 'https') ? 443 : 80;
			}
			$this->_uri = substr($req, $pos);
			if($this->_uri == '')
			{
				$this->_uri = '/';
			}
		}

		//constructor
		function Request($url)
		{
			$this->_url = $url;
			$this->_scan_url();
		}

		//download URL to string
		function DownloadToString()
		{
			$crlf = "\r\n";
			//generate request
			$response = '';
			$req = 'GET '.$this->_uri.' HTTP/1.0'.$crlf. 'Host: '.$this->_host.$crlf.
				'User-Agent: Mozilla/5.0 (Windows NT 6.0) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.75 Safari/535.7'.$crlf.$crlf;
			$this->_fp = fsockopen(($this->_protocol == 'https' ? 'ssl://' : '') . $this->_host, $this->_port);
			fwrite($this->_fp, $req);
			while(is_resource($this->_fp) && $this->_fp && !feof($this->_fp))
			{
				$response .= fread($this->_fp, 1024);
			}
			fclose($this->_fp);
			//split header and body
			$pos = strpos($response, $crlf.$crlf);
			if($pos === false)
			{
				return $response;
			}
			$header = substr($response, 0, $pos);
			$body = substr($response, $pos + 2*strlen($crlf));
			//parse headers
			$headers = array();
			$lines = explode($crlf, $header);
			foreach($lines as $line)
			{
				if(($pos = strpos($line, ':')) !== false)
				{
					$headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos+1));
				}
			}
			//redirection
			if(isset($headers['location']))
			{
				$http = new HTTPRequest($headers['location']);
				return($http->DownloadToString($http));
			}
			return $body;
		}

		function DownloadHeadersOnly()
		{
			$crlf = "\r\n";
			//generate request
			$response = '';
			$req = 'GET '.$this->_uri.' HTTP/1.0'.$crlf.'Host: '.$this->_host.$crlf.$crlf;
			$this->_fp = fsockopen(($this->_protocol == 'https' ? 'ssl://' : '') . $this->_host, $this->_port);
			fwrite($this->_fp, $req);
			while(is_resource($this->_fp) && $this->_fp && !feof($this->_fp))
			{
				$response .= fread($this->_fp, 1024);
			}
			fclose($this->_fp);
			//split header and body
			$pos = strpos($response, $crlf.$crlf);
			if($pos === false)
			{
				return $response;
			}
			$header = substr($response, 0, $pos);
			$body = substr($response, $pos + 2*strlen($crlf));
			$headers = array();
			$lines = explode($crlf, $header);
			foreach($lines as $line)
			{
				if(($pos = strpos($line, ':')) !== false)
				{
					$headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos+1));
				}
			}
			return $headers;
		}
	}
?>