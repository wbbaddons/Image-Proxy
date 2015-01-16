<?php
namespace wcf\system\cronjob;

use wcf\system\cronjob\AbstractCronjob; 
use wcf\data\cronjob\Cronjob;
use wcf\util\DirectoryUtil; 

class ProxyCleanupCronjob extends AbstractCronjob {
	/**
	 * @see	\wcf\system\cronjob\ICronjob::execute()
	 */
	public function execute(Cronjob $cronjob) {
		parent::execute($cronjob);
		
		if (!MODULE_PROXY) return; 
		
		$dir = WCF_DIR . 'images/proxy'; 
		
		$util = new DirectoryUtil($dir, true);
		foreach ($util->getFileObjects() as $obj) {
			if ($obj->getFilename() != '.htaccess' && $obj->getMTime() < TIME_NOW - (PROXY_STORE_TIME * 86400)) {
				@unlink($obj->getRealPath());
			}
		}
	}
}
