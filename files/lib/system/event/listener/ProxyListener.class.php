<?php
namespace wcf\system\event\listener; 

use wcf\util\Signer; 

class ProxyListener implements \wcf\system\event\IEventListener {
	
	/**
	 * @see	\wcf\system\event\IEventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName) {
		if (!MODULE_PROXY) return; 
		
		if (!$eventObj->message || \wcf\data\bbcode\BBCodeCache::getInstance()->getBBCodeByTag('img') === null) {
			return;
		}
		
		preg_match_all('~\[img\](https?:\/\/[a-zA-Z0-9\.:@\-]*\.?[a-zA-Z0-9äüö\-]+\.[A-Za-z]{2,8}(:[0-9]{1,4})?(\/[^#\]]*)?(#[^#]+)?)\[\/img\]~i', $eventObj->message, $matches, PREG_SET_ORDER);
		
		foreach ($matches as $match) {
			if (function_exists('gethostbyname')) {
				// is localhost? 
				$url = parse_url($match[1]);
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

				if (!$localhost && !\wcf\system\application\ApplicationHandler::getInstance()->isInternalURL($match[0])) {
					$eventObj->message = \wcf\util\StringUtil::replaceIgnoreCase($match[0], '[img]' . $this->buildImageURL($match[1]) . "[/img]", $eventObj->message);
				}
			}
		}
	}
	
	public static function buildImageURL($url) {
		return \wcf\system\request\LinkHandler::getInstance()->getLink('ImageProxy', array('image' => Signer::createSignedString($url)));
	}
}