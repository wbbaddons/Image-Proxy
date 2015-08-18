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
		
		$imgs = $this->getImgTags($eventObj->message);
		
		foreach ($imgs as $img) {
			if (function_exists('gethostbyname')) {
				// is localhost? 
				$url = parse_url($img['attributes'][0]);

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

				if (!$localhost && !\wcf\system\application\ApplicationHandler::getInstance()->isInternalURL($img['attributes'][0])) {
					$eventObj->message = \wcf\util\StringUtil::replaceIgnoreCase($img['match'], '[img=\''. $this->buildImageURL($img['attributes'][0]) .'\''. ((isset($img['attributes'][1])) ? ','.$img['attributes'][1] .((isset($img['attributes'][2])) ? ','.$img['attributes'][2] : ''): '') .'][/img]', $eventObj->message);
				}
			}
		}
	}
	
	/**
	 * Returns all img-bbcodes inside a given text. 
	 * 
	 * @author	Sebastian Zimmer
	 * @based on	wcf/lib/system/bbcode/BBCodeParser
	 *
	 * @param	string	$text
	 * @return	array
	 */
	
	public function getImgTags($text){
		$pattern = '~\[(?:/(?:img)|(?:img)
			(?:=
				(?:\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|[^,\]]*)
				(?:,(?:\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|[^,\]]*))*
			)?)\]~ix';
		
		// get bbcode tags
		preg_match_all($pattern, $text, $imgList, PREG_OFFSET_CAPTURE);
		
		$imgArray = array();
		
		foreach($imgList[0] as $num => $data){
			$img = array();
			//ignore closing tags
			if (mb_substr($data[0], 1, 1) != '/') {
				// split tag and attributes
				preg_match("!^\[([a-z0-9]+)=?(.*)]$!si", $data[0], $imgData);
				$img['name'] = mb_strtolower($imgData[1]);
				
				// build attributes
				if (!empty($imgData[2])) {
					preg_match_all("~(?:^|,)('[^'\\\\]*(?:\\\\.[^'\\\\]*)*'|[^,]*)~", $imgData[2], $attributes);
					
					// remove quotes
					for ($i = 0, $j = count($attributes[1]); $i < $j; $i++) {
						if (mb_substr($attributes[1][$i], 0, 1) == "'" && mb_substr($attributes[1][$i], -1) == "'") {
							$attributes[1][$i] = str_replace("\'", "'", $attributes[1][$i]);
							$attributes[1][$i] = str_replace("\\\\", "\\", $attributes[1][$i]);
							
							$attributes[1][$i] = mb_substr($attributes[1][$i], 1, -1);
						}
					}
					$img['attributes'] = $attributes[1];
				}
				
				$img['match'] = $data[0];
				
				// check next tag for closing tag...
				if(isset($imgList[0][$num+1]) && mb_substr($imgList[0][$num+1][0], 1, 1) == '/'){
					$start = $imgList[0][$num][1];
					$length = $imgList[0][$num+1][1]+strlen($imgList[0][$num+1][0])-$start;
					$img['match'] = mb_substr($text,$start,$length);
					
					//if no attribute found use content of the tags instead
					if(!isset($img['attributes']) || count($img['attributes'])==0){
						$start = $imgList[0][$num][1]+strlen($imgList[0][$num][0]);
						$length = $imgList[0][$num+1][1]-$start;
						$img['attributes'][0] = mb_substr($text,$start,$length);
					}
				}
				if(isset($img['attributes']) && !empty($img['attributes'])){
					$imgArray[] = $img;
				}
			}
		}
		return $imgArray;
	}
	
	public static function buildImageURL($url) {
		return \wcf\system\request\LinkHandler::getInstance()->getLink('ImageProxy', array('image' => Signer::createSignedString($url)));
	}
}
