<?php
namespace wcf\page; 
use wcf\system\event\EventHandler;
use wcf\util\Signer; 

/**
 * Displays a proxied image. 
 * 
 * @author	Joshua Rüsweg
 * @copyright	2015 Joshua Rüsweg
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	be.bastelstu.josh.imageproxy
 */
class ImageProxyPage extends \wcf\page\AbstractPage {
	
	/**
	 * @see \wcf\page\AbstractPage::$useTemplate
	 */
	public $useTemplate = false;
	
	/**
	 * @see \wcf\page\AbstractPage::$neededModules
	 */
	public $neededModules = array('MODULE_PROXY');
	
	/**
	 * The image url. 
	 * 
	 * @var String 
	 */
	public $url = null; 
	
	/**
	 * The signed image hash from the url. 
	 * 
	 * @var String 
	 */
	public $imageHash = null;
	
	/**
	 * If this text is in the file, the proxy displays a "NOT FOUND" message. 
	 * @var String
	 */
	const NOT_FOUND_TEXT = '';
	
	/**
	 * Valid image types. 
	 * 
	 * @var array<Integer> 
	 */
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
	 * Stores the image on our space. 
	 */
	public function store() {
		// first remove old cache image
		$this->removeImage(); 
		
		// replace blanks with %20
		$this->url = str_replace(' ', '%20', $this->url);
		
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
		EventHandler::getInstance()->fireAction($this, 'imageStored');
	}
	
	/**
	 * Returns true if the image is stored and not outdated. 
	 * 
	 * @return boolean
	 */
	public function isStored() {
		if (file_exists($this->getLocalLink()) && filemtime($this->getLocalLink()) > TIME_NOW - (PROXY_STORE_TIME * 86400)) {
			return true; 
		}
		
		return false; 
	}
	
	/**
	 * Returns the local dir for the image. 
	 * 
	 * @return String
	 */
	public function getLocalPath() {
		return WCF_DIR . 'images/proxy/' . substr(md5($this->url), 0, 2) . '/'; 
	}
	
	/**
	 * Returns the local image link. 
	 * 
	 * @return String
	 */
	public function getLocalLink() {
		return $this->getLocalPath() . md5($this->url);
	}
	
	/**
	 * Removes the image. 
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
