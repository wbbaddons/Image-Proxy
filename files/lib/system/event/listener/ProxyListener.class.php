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
		preg_match_all('~\[img\](?P<url>[^\]]*)\[\/img\]~i', $eventObj->message, $matches, PREG_SET_ORDER);
		
		// match all [img=link,(none|left|right|center),[0-9]+]
		preg_match_all("~\[img=\'(?P<url>[^\]]*)\',(?P<orientation>none|left|right|center),(?P<size>[0-9]+)\](\[\/img\])?~i", $eventObj->message, $matches2, PREG_SET_ORDER);
		
		// match all [img=link,(none|left|right|center)]
		preg_match_all("~\[img=\'(?P<url>[^\]]*)\',(?P<orientation>none|left|right|center)\](\[\/img\])?~i", $eventObj->message, $matches3, PREG_SET_ORDER);

		// match [img=link]
		preg_match_all("~\[img=\'?(?P<url>[^\,\]]*)\'?\](\[\/img\])?~i", $eventObj->message, $matches4, PREG_SET_ORDER);
		
		$matches = array_merge($matches, $matches2, $matches3, $matches4);
		
		foreach ($matches as $match) {
			if (function_exists('gethostbyname')) {
				// is localhost? 
				$url = parse_url($match['url']);
				
				if ($url === false) {
					// url isn't a url
					continue;
				}
				
				$host = @gethostbyname($url['host']);
				
				if (\wcf\system\Regex::compile('127.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}')->match($host)) {
					continue;
				}
				
				if (\wcf\system\Regex::compile('10.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}')->match($host)) {
					continue;
				}
				
				if (\wcf\system\Regex::compile('192.168.[0-9]{1,3}.[0-9]{1,3}')->match($host)) {
					continue;
				}
				
				if (\wcf\system\Regex::compile('172.16.[0-9]{1,3}.[0-9]{1,3}')->match($host)) {
					continue;
				}
				
				if (!\wcf\system\application\ApplicationHandler::getInstance()->isInternalURL($match['url'])) {
					$eventObj->message = \wcf\util\StringUtil::replaceIgnoreCase($match[0], '[img=\''. $this->buildImageURL($match['url']) .'\''. ((isset($match['orientation'])) ? ','.$match['orientation'].((isset($match['size'])) ? ','.$match['size'] : '') : '') .'][/img]', $eventObj->message);
				}
			}
		}
	}
	
	public static function buildImageURL($url) {
		return \wcf\system\request\LinkHandler::getInstance()->getLink('ImageProxy', array('image' => Signer::createSignedString($url)));
	}
}
