<?php
namespace wcf\system\event\listener; 
use wcf\util\Signer; 

/**
 * Replace images with proxy images. 
 * 
 * @author	Joshua Rüsweg
 * @copyright	2015 Joshua Rüsweg
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	be.bastelstu.josh.imageproxy
 */
class ProxyListener implements \wcf\system\event\listener\IParameterizedEventListener {
	
	/**
	 * @see	\wcf\system\event\IEventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		if (!MODULE_PROXY) return; 
		
		if (!$eventObj->message || \wcf\data\bbcode\BBCodeCache::getInstance()->getBBCodeByTag('img') === null) {
			return;
		}
		
		// match [img]link[/img]
		preg_match_all('~\[img\]([^\]]*)\[\/img\]~i', $eventObj->message, $matches, PREG_SET_ORDER);

		// match all [img=link,(left|right|center)]
		preg_match_all("~\[img=\'([^\]]*)\',(left|right|center)\]\[\/img\]~i", $eventObj->message, $matches2, PREG_SET_ORDER);

		// match [img=link]
		preg_match_all("~\[img=\'?([^\,\]]*)\'?\]~i", $eventObj->message, $matches3, PREG_SET_ORDER);

		$matches = array_merge($matches, $matches2, $matches3);

		foreach ($matches as $match) {
			if (function_exists('gethostbyname')) {
				// is localhost? 
				$url = parse_url($match[1]);

				if ($url === false) {
					// url isn't a url
					continue;
				}

				$host = @gethostbyname($url['host']); 
				$localhost = false; 

				if (\wcf\system\Regex::compile('127.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}')->match($host)) {
					$localhost = true; 
				}

				if (\wcf\system\Regex::compile('10.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}')->match($host)) {
					$localhost = true; 
				}

				if (\wcf\system\Regex::compile('192.168.[0-9]{1,3}.[0-9]{1,3}')->match($host)) {
					$localhost = true; 
				}

				if (\wcf\system\Regex::compile('172.16.[0-9]{1,3}.[0-9]{1,3}')->match($host)) {
					$localhost = true; 
				}

				if (!$localhost && !\wcf\system\application\ApplicationHandler::getInstance()->isInternalURL($match[1])) {
					$eventObj->message = \wcf\util\StringUtil::replaceIgnoreCase($match[0], '[img=\''. $this->buildImageURL($match[1]) .'\''. ((isset($match[2])) ? ','.$match[2] : '') .'][/img]', $eventObj->message);
				}
			}
		}
	}
	
	public static function buildImageURL($url) {
		return \wcf\system\request\LinkHandler::getInstance()->getLink('ImageProxy', array('image' => Signer::createSignedString($url)));
	}
}
