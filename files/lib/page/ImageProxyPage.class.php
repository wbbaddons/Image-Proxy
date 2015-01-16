<?php
namespace wcf\page; 

use wcf\util\Signer; 

class ImageProxyPage extends \wcf\page\AbstractPage {
	
	/**
	 * @see \wcf\page\AbstractPage::$useTemplate
	 */
	public $useTemplate = false;
	
	/**
	 * @see \wcf\page\AbstractPage::$neededModules
	 */
	public $neededModules = array('MODULE_PROXY');
	
	public $url = null; 
	
	public $imageHash = null; 
	
	public $localimage = null;
	
	const NOT_FOUND_TEXT = ''; // Bob Kelso (Scrubs [Season 4 Episode 20])
	
	public static $validImageTypes = array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP);
	
	/**
	 * @see	\wcf\page\IPage::readParameters()
	 */
	public function readParameters() {
		parent::readParameters();
		
		if (isset($_GET['image'])) $this->imageHash = $_GET['image']; 
		
		$this->validate(); 
	}
	
	/**
	 * validate the given resources
	 */
	public function validate() {
		if (empty($this->imageHash)) {
			throw new \wcf\system\exception\IllegalLinkException(); 
		}
		
		$this->url = Signer::getValueFromSignedString($this->imageHash);
		
		if ($this->url === null) {
			throw new \wcf\system\exception\IllegalLinkException(); 
		}
	}
	
	/**
	 * store the image
	 */
	public function store() {
		// first remove old cache image
		$this->removeImage(); 
		
		try {
			$proxy = new \wcf\util\HTTPRequest($this->url);
			$proxy->execute(); 
			$reply = $proxy->getReply(); 
			$imagestring = $reply['body']; 
			
			// $img = getimagesizefromstring($imagestring); <- only in 5.4 or higher
			// workaround: https://gist.github.com/t-cyrill/6109550#file-getimagesizefromstring-php
			$uri = 'data://application/octet-stream;base64,' . base64_encode($imagestring);
			$img = getimagesize($uri);
			
			if (!in_array($img[2], self::$validImageTypes)) {
				throw new \wcf\system\exception\SystemException('not valid image-type');
			}
		} catch (\Exception $e) {
			$imagestring = self::NOT_FOUND_TEXT; 
		}
		
		\wcf\util\FileUtil::makePath($this->getLocalPath());
		
		if (@file_put_contents($this->getLocalLink(), $imagestring) === false) {
			throw new \wcf\system\exception\SystemException('cannot store proxyimage');
		}
	}
	
	/**
	 * Is the file already stored? 
	 * @return boolean
	 */
	public function isStored() {
		if (file_exists($this->getLocalLink()) && filemtime($this->getLocalLink()) > TIME_NOW - (PROXY_STORE_TIME * 86400)) {
			return true; 
		}
		
		return false; 
	}
	
	public function getLocalPath() {
		return WCF_DIR . 'images/proxy/' . substr(md5($this->url), 0, 2) . '/'; 
	}
	
	/**
	 * get the local link
	 * @return String
	 */
	public function getLocalLink() {
		return $this->getLocalPath() . md5($this->url);
	}
	
	/**
	 * remove the image
	 */
	public function removeImage() {
		if (file_exists($this->getLocalLink())) {
			if (@unlink($this->getLocalLink()) === false) {
				throw new \wcf\system\exception\SystemException('cannot remove local image ('. $this->getLocalLink() .')');
			}
		}
	}
	
	/**
	 * @see	\wcf\page\IPage::show()
	 */
	public function show() {
		parent::show(); 
		
		if (!$this->isStored()) {
			$this->store(); 
		}
		
		if (filesize($this->getLocalLink()) == 0) {
			throw new \wcf\system\exception\IllegalLinkException();
		}
		
		// etag caching
		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && str_replace('"', '', $_SERVER['HTTP_IF_NONE_MATCH']) == md5($this->getLocalLink() . filemtime($this->getLocalLink()))) {
			@header('HTTP/1.1 304 Not Modified');
			exit;
		}
		
		$img = getimagesize($this->getLocalLink());
		
		$reader = new \wcf\util\FileReader($this->getLocalLink(), array('showInline' => true, 'mimeType' => $img['mime']));
		$reader->addHeader('ETag', '"'.md5($this->getLocalLink() . filemtime($this->getLocalLink())).'"');
		$reader->send(); 
		exit; 
	}
}