<?PHP
/**
 * Update-Modul für Semknox-Search
 * test cli: in Shopware-Verzeichnis sudo -u www-data php bin/console sw:cron:run SemknoxUpdateDataJob -f 
 */
namespace semknoxSearch\bin;
use semknoxSearch\Bundle\SearchBundle\semknoxItemData;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use semknoxSearch\bin\wosoLogger; 
class semknoxUpdate {
		/**
		*	@var \semknoxSearch\Bundle\SearchBundle\semknoxBaseApiV3
		*/
		private $semknoxBaseApi;
		private $dbLastinsertID = 0;
		/**
		*	@var array
		*/
		private $config;
		private $customerGroup='';
		private $sendBruttoPrice=0;
		private $articleIdList=array();
		private $articleList=null;
		private $articleDeleteList=null;
		private $productService = null;
		private $context = null;
		private $contexti = null;
		private $mediservice = null;
		private $bestThumbNo = -11;
		private $domainurl='/';
		private $picurl='';
		private $picurl2='';
		private $shop_id=0;
		private $shopData=array();
		private $shopCategory = 0; 
		private $exportfields=array("ArticleNumber",
											"MasterArticleNumber",
											"Title",
											"Description",
											"DescriptionPlain",
											"ShortDescription",
											"Deeplink",
											"ImageURL",
											"Price",
											"Currency",
											"Manufacturer",
											"Attributes",
											"CategoryPath",
											"Position", 
											"ShippingTime",
											"ShippingCosts",
											"Availability",
											"PseudoPrice",
											"GroupId",
											"Accessories",
											"ImageURL2",
											"ImageURL3",
											"ImageURL4",
											"ImageURL5",
											"ImageURL6",
											"ImageURL7",
											"ImageURL8",
											"ImageURL9",
											"ImageURL10",
											"Zustand",
											"Gewicht",
											"Tiefe",
											"Breite",
											"Hoehe",
											"Farbe",
											"Material",
											"Holzart",
											"isAccessorie",
											"Ausschreibungstext",
											"Basiskategorie",
											);
		private $KatPathDelimiter = ' # ';
		private $defAttributes = 0;       
		public $error = 0; 
		private $loggerObj = null;
    /**
     */
    public function __construct($shopid) {
            $this->shop_id=$shopid;
            $this->loggerObj = new wosoLogger();
            if ($this->shop_id<=0) { echo "no Shop-ID!\n"; exit(); }
    		$this->getConfig();
    		$this->semknoxBaseApi = new \semknoxSearch\Bundle\SearchBundle\semknoxBaseApiV3($this->config['semknoxBaseUrl'], $this->config['semknoxCustomerId'], $this->config['semknoxApiKey'], "updatesession", $this->config['semknoxUseGrouped'], $this->config['semknoxUseHeadOnly']);
    		$this->semknoxBaseApi->addHeaderInfoData($this->getHeaderInfoData());
    		$this->articleList=new \semknoxSearch\Bundle\SearchBundle\semknoxItemDatalist();
    		$this->articleDeleteList=new \semknoxSearch\Bundle\SearchBundle\semknoxItemDatalist();
    		$this->getDomainURL();
				$repository = Shopware()->Container()->get('models')->getRepository('Shopware\Models\Shop\Shop');
				$shop = $repository->getActiveById($this->shop_id);
				if (is_null($shop)) { $this->error=-11111; return -1; }
				$shop->registerResources();
				$this->shopCategory=$shop->getCategory()->getId();
				$this->shopData=$this->getShopData($this->shop_id);				
				/* 
				$this->context = Context::createFromShop($shop, Shopware()->Container()->get('config'));
				var_dump($this->context); */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->createShopContext(
        	$shop->getId(),
        	$shop->getCurrency()->getId(),
	        ContextService::FALLBACK_CUSTOMER_GROUP
				);
				$this->contexti=Shopware()->Container()->get('shopware_storefront.context_service')->getShopContext();
      	$this->mediaservice=Shopware()->Container()->get('shopware_storefront.media_service');
    }
    public function initErrorHandler()
    {
        register_shutdown_function(function()
        {
            $this->articleList->clearList();
            $this->articleDeleteList->clearList();
            file_put_contents('./var/log/semkerror.js','DELETE', FILE_APPEND);
            if ((!is_null($err = error_get_last())) && (!in_array($err['type'], array (E_NOTICE, E_WARNING))))
            {
                file_put_contents('./var/log/semkerror.js',serialize($err), FILE_APPEND);
            }
        });
    }
    private function getConfigSelectIntValue($v,$def=0) {
    	$ret=$v;
			if (is_bool($v)) { 
				$v ?  $ret=1 : $ret=0;
			} else {
				if (trim($v)=='') { $ret=$def; } else {
					if (substr($v,0,6)=='_woso_') { $v=substr($v,6); }
					if (!(ctype_digit($v))) { $ret=$def; } else { $ret=intval($v); }					
				}
			}
    	return $ret;
    }
    private function getConfigValue($cfgid, $dba, $doint=0, $default='') {
    	if (isset($dba[$cfgid])) { $ret=$dba[$cfgid]; } 
    		else { $ret = Shopware()->Config()->getByNamespace('semknoxSearch',$cfgid); }
    	if ($doint) {$ret=$this->getConfigSelectIntValue($ret, $default); }
    	return $ret;
    }
    private function getVersionInt($version) {
        $h1=explode('.',$version);
        $ret=array();
        foreach($h1 as $v) {
            if (is_numeric($v)) { $ret[] = intval($v); }
        }
        return $ret;
    }
    /** wird nicht benötigt, da version_compare!
     * Funktion gibt 1 zurück, wenn übergebene Version kleiner ist als Shopware-Version, sonst 0
     * @param string $minVer
     * @param int $default - sollte keine Shopware-Version bekannt sein, wird Default zurückgegeben
     */
    private function shopVersionLess($minVer, $default=0) {
        $minVerInt=$this->getVersionInt($minVer);
        $ret=0;
        if (count($this->config['shopwareversionInt'])<2) { 
            return $default;
        }
        $ret=1;
        foreach ($minVerInt as $k => $v) {
            if (isset($this->config['shopwareversionInt'][$k])) { $vs=$this->config['shopwareversionInt'][$k]; } else { $vs=0; }
            echo "\n<br>$k $v $vs";
            if ($vs >= $v) { $ret=0; break; }
        }
        return $ret;
    }
    private function getHeaderInfoData() {
        $ip = '';
        if (!empty($_SERVER['REMOTE_ADDR'])) { $ip = $_SERVER['REMOTE_ADDR']; }
        $ret= [
            'shopsys' => 'SHOPWARE',
            'shopsysver' => $this->config['shopwareversion'],
            'clientip' => $ip,
            'sessionid'=>$this->config['sessionID'],
            'extver' => $this->config['pluginVersion']
        ];
        return $ret;
    }
    private function getConfig() {
    		$this->config=array();
	    	$sql = "SELECT e.name, e.value, v.value as value2 FROM `s_core_config_forms` cf, s_core_config_elements e left JOIN s_core_config_values v ON (v.element_id=e.id)  AND ((shop_id='".$this->shop_id."') OR (ISNULL(shop_id))) WHERE cf.name='semknoxSearch' and e.form_id = cf.id ";
  	    $res = Shopware()->Db()->fetchAll($sql);    
  	    $hv=array();
  	    foreach($res as $cf) {  	    	
  	    	if ($cf['value']) { $cf['value']=unserialize($cf['value']); }
  	    	if ($cf['value2']) { $cf['value2']=unserialize($cf['value2']); }
 	    		if (!is_null($cf['value2'])) {$hv[$cf['name']]=$cf['value2']; }
 	    			else { if (!is_null($cf['value'])) {$hv[$cf['name']]=$cf['value']; } } 
  	    }
  	        $this->config['semknoxActivate'] = $this->getConfigValue('semknoxActivate',$hv,1,0);
  	        $this->config['semknoxBaseUrl'] = "https://api-shopware.sitesearch360.com/";
    		$this->config['semknoxCustomerId'] = $this->getConfigValue('semknoxCustomerId', $hv);
    		$this->config['semknoxApiKey'] = $this->getConfigValue('semknoxApiKey', $hv);
    		$this->config['semknoxRewriteCat'] = $this->getConfigValue('semknoxRewriteCat',$hv,1,0);
    		$this->config['semknoxUseGrouped'] = $this->getConfigValue('semknoxUseGrouped',$hv,1,0);
     		$this->config['semknoxGroupID'] = $this->getConfigValue('semknoxGroupID',$hv,1,0);
    		$this->config['semknoxUseHeadOnly'] = $this->getConfigValue('semknoxUseHeadOnly',$hv,1,1);
    		$this->config['semknoxUseVariantConfigAsFlag'] = $this->getConfigValue('semknoxUseVariantConfigAsFlag',$hv,1,0);    		
    		$this->config['semknoxRankingAttribute'] = $this->getConfigValue('semknoxRankingAttribute',$hv);
    		$this->config['semknoxRedirCode'] = $this->getConfigValue('semknoxRedirCode',$hv);
     		$this->config['semknoxUpdateTemplates'] = $this->getConfigValue('semknoxUpdateTemplates',$hv,1,0);
    		$this->config['semknoxUpdateSingle'] = $this->getConfigValue('semknoxUpdateSingle',$hv,1,0);
    		$this->config['semknoxUpdateMaxItems']=intval($this->getConfigValue('semknoxUpdateMaxItems',$hv)); 
    		$this->config['semknoxUseAutosug'] = 1;
    		$this->config['semknoxUseFallback'] = $this->getConfigValue('semknoxUseFallback',$hv,1,1); 
    		$this->config['semknoxAppendNoStock'] = $this->getConfigValue('semknoxAppendNoStock',$hv,1,0);
    		$this->config['semknoxUseOnlySEOCats'] = $this->getConfigValue('semknoxUseOnlySEOCats',$hv,1,0);
    		$this->config['semknoxDeleteNoStock'] = $this->getConfigValue('semknoxDeleteNoStock',$hv,1,0);    		
    		$this->config['semknoxPreferPrimaryVariant'] = $this->getConfigValue('semknoxPreferPrimaryVariant',$hv,1,0);
    		$this->config['hideNoStock'] = Shopware()->Config()->get('hideNoInStock');
    		$this->config['shopwareversion'] = Shopware()->Config()->get('version');
    		$this->config['shopwareversionInt']=$this->getVersionInt($this->config['shopwareversion']);
    		$this->config['semknoxUpdateMaxCatParents']=intval($this->getConfigValue('semknoxUpdateMaxCatParents',$hv));
    		$this->config['semknoxUpdateCatTitleGlue'] = $this->getConfigValue('semknoxUpdateCatTitleGlue',$hv);
    		$this->config['sessionID'] = "updatesession";
    		$this->config['pluginVersion'] = '1.9.31';
    		if (trim($this->config['semknoxRedirCode'])=='') { $this->config['semknoxRedirCode']='302'; }
    		$this->config['semknoxRedirCode']=intval($this->config['semknoxRedirCode']);
    		if (($this->config['semknoxRedirCode']<200) || ($this->config['semknoxRedirCode']>600)) { $this->config['semknoxRedirCode']=302; }
				if ($this->config['semknoxUpdateMaxItems']<=0) { $this->config['semknoxUpdateMaxItems'] = 5000; }
			$this->config['semknoxUseDate']=0;
			if (trim($this->config['semknoxRankingAttribute'])=='') { $this->config['semknoxUseDate']=1; $this->config['semknoxRankingAttribute']=' If( isnull(d.releaseDate), UNIX_TIMESTAMP(a.changetime), UNIX_TIMESTAMP(d.releaseDate)) '; }
			$this->config['semknoxDebugfile'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxDebugfile'));
    }        
    private function getDomainURL () {
    	$this->domainurl="";
    	$sql = "SELECT * FROM s_core_shops WHERE active='1' AND `default`='1' ORDER BY position, id";
        $res = Shopware()->Db()->fetchAll($sql);    
		if (empty($res)) { return ""; }    	
		$h='http://';
		if ( ($res[0]['secure']==1) ) { $h='https://'; }
		$h.=$res[0]['host'].'/';
		$this->domainurl=$h;
		if (!empty($this->shop_id)) {
		    $sql = "SELECT * FROM s_core_shops WHERE id = '".$this->shop_id."' ";
		    $res = Shopware()->Db()->fetchAll($sql);
		    if (empty($res)) { return ""; }
		    $h='http://';
		    if ( ($res[0]['secure']==1) ) { $h='https://'; }
		    $h.=$res[0]['host'].'/';
		    $this->domainurl=$h;		    
		}
		return $h;
    }    
    private function mergeFiles() {
    		$this->articleList->clearList();
    		$this->articleDeleteList->clearList();
    		for ($i=1; $i < 100000; $i++) {
    			$found=0;
    			$f='./var/log/semkDBArticleList'.str_pad($i, 5 ,'0', STR_PAD_LEFT).'.js';
    			if (file_exists($f)) { 
    				$found++; 
	   				$f=file_get_contents($f);
	   				$list=unserialize($f);unset($f);
	   				foreach($list->list as $item) {
	   					$this->articleList->addItem($item);
	   				}
	   				unset($list);
    			}
    			if ($found==0) { break; }
    		}
    		/*
    		for ($i=1; $i < 100000; $i++) {
    			$found=0;
    			$f='./var/log/semkDBArticleDelList'.str_pad($i, 5 ,'0', STR_PAD_LEFT).'.js';
    			if (file_exists($f)) { 
    				$found++; 
	   				$f=file_get_contents($f);
	   				$list=unserialize($f);unset($f);
	   				foreach($list->list as $item) {
	   					$this->articleDeleteList->addItem($item);
	   				}
	   				unset($list);
    			}
    			if ($found==0) { break; }
    		} 
    		*/
    }
    private function cleanUpFiles() {
    		if (file_exists('./var/log/semkLock.js')) {unlink('./var/log/semkLock.js');}
    		if (file_exists('./var/log/semkDBId.js')) {unlink('./var/log/semkDBId.js');}
    		if (file_exists('./var/log/semkDBNextPos.js')) {unlink('./var/log/semkDBNextPos.js'); }
    		if (file_exists('./var/log/semkDBArticleList.js')) {unlink('./var/log/semkDBArticleList.js'); }
    		if (file_exists('./var/log/semkDBArticleDelList.js')) {unlink('./var/log/semkDBArticleDelList.js'); }    		
    		for ($i=1; $i < 100000; $i++) {
    			$ffound=0;
    			$f='./var/log/semkDBArticleList'.str_pad($i, 5 ,'0', STR_PAD_LEFT).'.js';
    			if (file_exists($f)) { $ffound++; unlink($f); }
    			$f='./var/log/semkDBArticleDelList'.str_pad($i, 5 ,'0', STR_PAD_LEFT).'.js';
    			if (file_exists($f)) { $ffound++; unlink($f); }
    			if ($ffound==0) { break; }
    		}
    }
    private function checkBrokenUpdate() {
    	$sql = "SELECT * from semknox_logs WHERE logtype=1 ORDER BY id DESC LIMIT 1";
    	$res = Shopware()->Db()->fetchAll($sql);  
    	if ( (count($res)>0) && ($res[0]['status'] < 100) && (strtotime($res[0]['logtime']) < strtotime('-1 day')) ) {
    	    $this->loggerObj->info("semknoxUpdate: found running Update in DB - cleaning up!");
    		$this->cleanUpFiles();
				$sql = "INSERT INTO semknox_logs (logtype, status, logtitle, logdescr) VALUES (1,111,'','')";
				$res = Shopware()->Db()->exec($sql);    		
    	}
    	if ( (file_exists('./var/log/semkDBId.js')) ) { 
    			$h=filemtime('./var/log/semkDBId.js');
    			if (($h!==false) && ((time()-$h)>86400) ) {
    			    $this->loggerObj->info("semknoxUpdate: found running Update in File - cleaning up!"); 
    				$this->cleanUpFiles();  	  				
    			}
    	} 
    }
    /**
    	aktualisiert alle Artikel eines Shops
    */
    public function updateAll($maxItems=0, $dostart=1, $isCron=0) {
        $this->loggerObj->info("semknoxUpdate: updateAll($maxItems, $dostart, $isCron)");
    	if (($dostart==1)) {
    		$this->checkBrokenUpdate();
    		$sql = "DELETE FROM semknox_logs WHERE logtype=111";
    		$res = Shopware()->Db()->exec($sql);      		
    	}
    	$sql = "SELECT * from semknox_logs WHERE logtype=1 ORDER BY id DESC LIMIT 1";
    	$res = Shopware()->Db()->fetchAll($sql);  
    	$this->dbLastinsertID=0;$this->articleIdList=array();
    	$this->articleList->clearList();
    	$this->articleDeleteList->clearList();
    	$aIdLoaded=0;$updateNextPos=0;$updateNextFile=1;
    	if ( (count($res)>0) && ($res[0]['status']<100) && ($maxItems>0) ) {
    	    if (($res[0]['status'] > 20) && ($res[0]['status'] < 30)) { $this->loggerObj->info("semknoxUpdate: err: already running single-Update!"); return; }
    	    if (($res[0]['status'] > 10) && ($res[0]['status'] < 20)) { $this->loggerObj->info("semknoxUpdate: err: already running cron-Update!"); return; }
    	    if ($res[0]['status'] < 2) { $this->loggerObj->info("semknoxUpdate: err: already running DB!"); return; }
    	    if (file_exists('./var/log/semkLock.js')) { $this->loggerObj->info("semknoxUpdate: err: already running File!"); return; } 
    		if (file_exists('./var/log/semkDBId.js')) {
   				$f=file_get_contents('./var/log/semkDBId.js');
   				$this->dbLastinsertID=unserialize($f);
    		} 
    		if ($this->dbLastinsertID) {
	    		if (file_exists('./var/log/semkDBArticleIDList.js')) {
	    			$aIdLoaded=1;
	   				$f=file_get_contents('./var/log/semkDBArticleIDList.js');
	   				$this->articleIdList=unserialize($f);
	    		} else {
	    			return;
	    		}    			
    		} else if ($dostart==0) { return; }
    		if (count($this->articleIdList)) {
	    		if (file_exists('./var/log/semkDBNextPos.js')) {
	   				$f=file_get_contents('./var/log/semkDBNextPos.js');
	   				$h=unserialize($f);
	   				$updateNextPos=$h['nextPos'];
	   				$updateNextFile=$h['nextFile'];
	    		}
	    		/*
	    		if (file_exists('./var/log/semkDBArticleList.js')) {
	   				$f=file_get_contents('./var/log/semkDBArticleList.js');
	   				$this->articleList=unserialize($f);
	    		}     			    			
	    		if (file_exists('./var/log/semkDBArticleDelList.js')) {
	   				$f=file_get_contents('./var/log/semkDBArticleDelList.js');
	   				$this->articleDeleteList=unserialize($f);
	    		} 
	    		*/    			    			
    		} 
    	} else {
    	    $this->loggerObj->info("semknoxUpdate: neues Update");
    		if ($dostart==0) return;
    		if ( (count($res)>0) && ($res[0]['status'] < 100) ) return;
    		$this->cleanUpFiles();
    	}
    	if ($this->dbLastinsertID==0) { 
    		$stat=($isCron*10)+1;
				$sql = "INSERT INTO semknox_logs (logtype, status, logtitle, logdescr) VALUES (1, $stat, '', '')";
				$res = Shopware()->Db()->exec($sql);  
				$this->dbLastinsertID = Shopware()->Db()->lastInsertId();			    				
				$stat=($isCron*10)+2;
				$sql = "INSERT INTO semknox_logs (logtype, status, logtitle, logdescr) VALUES (1,$stat,'Artikelliste aufbauen','0%')";
				$res = Shopware()->Db()->exec($sql);  
				$this->dbLastinsertID = Shopware()->Db()->lastInsertId();		
				if ($maxItems>0) {	    				
	 				file_put_contents('./var/log/semkDBId.js', serialize($this->dbLastinsertID));
	 			}
			}
    	if ($aIdLoaded==0) {
	    	file_put_contents('./var/log/semkLock.js', "1");    		
	    	$this->getArticleList();
	    	if ($maxItems>0) {
 					file_put_contents('./var/log/semkDBArticleIDList.js', serialize($this->articleIdList));
 				}
    		$logdes='1/'.count($this->articleIdList);
    		$stat=($isCron*10)+2;
				$sql = "UPDATE semknox_logs SET logtype='1', status='$stat', logtitle='SEMKNOX Daten aufbauen', logdescr='$logdes' WHERE id=".$this->dbLastinsertID;
				$res = Shopware()->Db()->exec($sql);  
	    	unlink('./var/log/semkLock.js');
				if ($maxItems>0) { return; }
 			}
    	$min=0;$max=count($this->articleIdList);
    	if ($maxItems) {
    		$min=$updateNextPos;$max=$min+$maxItems-1;
    		if ($max>count($this->articleIdList)) { $max = count($this->articleIdList); }
    	}
    	if ($min < $max) {
	    	file_put_contents('./var/log/semkLock.js', "1");
	    	$t1=microtime(true);
	    try {
  	  	  $this->createArticleDataList($min, $max);
	    } catch ( \Exception $e) {
	        $this->loggerObj->error("semknoxUpdate: err: CreateArticleData: ".$e->getMessage());
	    }
  	  	$t2=microtime(true);
  	  	$te=$t2-$t1;
  	  	$logdes=$max.'/'.count($this->articleIdList);
				$sql = "UPDATE semknox_logs SET logdescr='$logdes' WHERE id=".$this->dbLastinsertID;
				try {
        			file_put_contents('./var/log/semkDBArticleList'.str_pad($updateNextFile, 5 ,'0', STR_PAD_LEFT).'.js', serialize($this->articleList));
        			file_put_contents('./var/log/semkDBArticleDelList'.str_pad($updateNextFile, 5 ,'0', STR_PAD_LEFT).'.js', serialize($this->articleDeleteList));
        			$updateNextPos=$max+1;$updateNextFile++;
     					file_put_contents('./var/log/semkDBNextPos.js', serialize(array('nextPos'=>$updateNextPos, 'nextFile'=>$updateNextFile)));
    				$res = Shopware()->Db()->exec($sql);  
        		    unlink('./var/log/semkLock.js');
				} catch ( \Exception $e) {
				    $this->loggerObj->error("semknoxUpdate: err: UpdateAll 1: ".$e->getMessage());				    
				}
				if ($maxItems>0) { return; }
			}
    	if ($max >= count($this->articleIdList)) {
    	    $this->loggerObj->info("semknoxUpdate: articleIDlist: ".count($this->articleIdList));
    		$stat=($isCron*10)+2;
				$sql = "UPDATE semknox_logs SET logtype='1', status='$stat', logtitle='SEMKNOX Daten senden', logdescr='' WHERE id=".$this->dbLastinsertID;
				$res = Shopware()->Db()->exec($sql);  
				file_put_contents('./var/log/semkLock.js', "1");
				$this->mergeFiles();
				$this->cleanUpFiles();
				file_put_contents('./var/log/semkLock.js', "1");
				$this->loggerObj->info("semknoxUpdate: articlelist: ".count($this->articleList->list)." articleDeleteList: ".count($this->articleDeleteList->list));
    		$res=$this->sendAll();    
	    	$st=101;if ($res['resultCode']<0) { $st=102; if (trim($res['resultText'])=='') { $res['resultText']=$res['message']; } $res['resultText'].=' resultCode: '.$res['resultCode']; $res['resultText'].=' Status: '.$res['status']; }
				$sql = "INSERT INTO semknox_logs (logtype, status, logdescr, logtitle) VALUES (?, ?, ?, '')";
				$res = Shopware()->DB()->query($sql, array(1, $st, $res['resultText']));
				$sql = "DELETE FROM semknox_logs WHERE id=".$this->dbLastinsertID;
				$res = Shopware()->Db()->exec($sql); 
				$this->loggerObj->info('semknoxUpdate: '.$res['resultText']);
				$this->cleanUpFiles();
				$this->loggerObj->info("semknoxUpdate: all Data sent to ".$this->config['semknoxBaseUrl']);
			}
    }
    /**
     * Test, ob gerade ein Update läuft
     * @return number -1=not running >-1=running 0=Backend 1=cron 2=single
     */
    public function isUpdateRunning() {
        $ret=-1;
        $sql = "SELECT * from semknox_logs WHERE logtype=1 ORDER BY id DESC LIMIT 1";
        $res = Shopware()->Db()->fetchAll($sql);
        if ( (count($res)>0) && ($res[0]['status']<100) ) {
            $ret=intval($res[0]['status']/10); 
        }
        return $ret;
    }    
    /**
     * aktualisiert alle Artikel eines Shops im Batch-Modus
     * @param number $maxItems
     * @param number $dostart
     * @param number $isCron
     * @return array $ret 
     */
        public function updateAllBatch($maxItems=0, $dostart=1, $isCron=0) {
            if (intval($maxItems)<=50) { $maxItems=50; }
            $ret=array();$ret['code']=-1;$ret['info']='';
            $this->loggerObj->info("semknoxUpdate: updateAllBatch($maxItems, $dostart, $isCron)");
            if (($dostart==1)) {
                $this->checkBrokenUpdate();
                $sql = "DELETE FROM semknox_logs WHERE logtype=111";
                $res = Shopware()->Db()->exec($sql);
            }
            $sql = "SELECT * from semknox_logs WHERE logtype=1 ORDER BY id DESC LIMIT 1";
            $res = Shopware()->Db()->fetchAll($sql);
            $this->dbLastinsertID=0;$this->articleIdList=array();
            $this->articleList->clearList();
            $this->articleDeleteList->clearList();
            $aIdLoaded=0;$updateNextPos=0;$updateNextFile=1;
            if ( $this->isUpdateRunning()>-1 ) {
                if ( ($dostart) ) { $this->loggerObj->error("semknoxUpdate: err: already running Update!"); $ret['info']='Update stopped, already running update!'; return $ret; }
                if (file_exists('./var/log/semkLock.js')) { $this->loggerObj->error("semknoxUpdate: err: already running File!"); $ret['info']='Update stopped, already running file!'; return $ret; }
                if (file_exists('./var/log/semkDBId.js')) {
                    $f=file_get_contents('./var/log/semkDBId.js');
                    $this->dbLastinsertID=unserialize($f);
                }
                if ($this->dbLastinsertID) {
                    if (file_exists('./var/log/semkDBArticleIDList.js')) {
                        $aIdLoaded=1;
                        $f=file_get_contents('./var/log/semkDBArticleIDList.js');
                        $this->articleIdList=unserialize($f);
                    } else {
                        $ret['info']='DBid found, but no articlelist!';
                        return $ret;
                    }
                } else if ($dostart==0) { $this->loggerObj->error("semknoxUpdate: No DBId found, but not starting!"); $ret['info']='No DBId found, but we are not starting!'; return $ret; }
                if (count($this->articleIdList)) {
                    if (file_exists('./var/log/semkDBNextPos.js')) {
                        $f=file_get_contents('./var/log/semkDBNextPos.js');
                        $h=unserialize($f);
                        $updateNextPos=$h['nextPos'];
                        $updateNextFile=$h['nextFile'];
                    }
                }
            } else {
                $this->loggerObj->info("semknoxUpdate: neues Update");
                if ($dostart==0) { $this->loggerObj->error("semknoxUpdate: no Data, but we are not starting!"); $ret['info']='No Data but we are not starting!'; return $ret; }
                $this->cleanUpFiles();
            }
            if ($this->dbLastinsertID==0) {
                $stat=($isCron*10)+1;
                $sql = "INSERT INTO semknox_logs (logtype, status) VALUES (1, $stat)";
                $res = Shopware()->Db()->exec($sql);
                $this->dbLastinsertID = Shopware()->Db()->lastInsertId();
                $stat=($isCron*10)+2;
                $sql = "INSERT INTO semknox_logs (logtype, status, logtitle, logdescr) VALUES (1,$stat,'Artikelliste aufbauen','0%')";
                $res = Shopware()->Db()->exec($sql);
                $this->dbLastinsertID = Shopware()->Db()->lastInsertId();
                if ($maxItems>0) {
                    file_put_contents('./var/log/semkDBId.js', serialize($this->dbLastinsertID));
                }
                $res=$this->semknoxBaseApi->startBatchUpload();                
                $st=($isCron*10)+2;$logdes='Starte BatchUpdate';
                if ( (!is_array($res)) || ($res['status']!='success') ) { 
                    $logdes='Fehler bei BatchUpdateStart'; $st=102;
                    $sql = "INSERT INTO semknox_logs (logtype, status, logdescr) VALUES (?, ?, ?)";
                    $res = Shopware()->DB()->query($sql, array(1, $st, $logdes));
                    $this->cleanUpFiles();
                    $ret['info']='Fehler bei BatchUpdateStart';
                    $this->loggerObj->error("semknoxUpdate: Fehler bei BatchUpdateStart");
                    return $ret;
                }
                $sql = "UPDATE semknox_logs SET logtype='1', status='$st', logtitle='$logdes' WHERE id=".$this->dbLastinsertID;
                $res = Shopware()->Db()->exec($sql);
            }
            if ($aIdLoaded==0) {
                file_put_contents('./var/log/semkLock.js', "1");
                $this->getArticleList();
                if ($maxItems>0) {
                    file_put_contents('./var/log/semkDBArticleIDList.js', serialize($this->articleIdList));
                }
                $logdes='1/'.count($this->articleIdList);
                $stat=($isCron*10)+2;
                $sql = "UPDATE semknox_logs SET logtype='1', status='$stat', logtitle='SEMKNOX Daten senden', logdescr='$logdes' WHERE id=".$this->dbLastinsertID;
                $res = Shopware()->Db()->exec($sql);
                unlink('./var/log/semkLock.js');
                if ($maxItems>0) { $ret['info']='Itemliste erstellt'; $ret['code']=count($this->articleIdList); return $ret; }
            }
            $min=0;$max=count($this->articleIdList);
            if ($maxItems) {
                $min=$updateNextPos;$max=$min+$maxItems;
                if ($max>count($this->articleIdList)) { $max = count($this->articleIdList); }
            }
            if ($min < $max) {
                file_put_contents('./var/log/semkLock.js', "1");
                $t1=microtime(true);
                try {
                    $this->createArticleDataList($min, $max);
                } catch ( \Exception $e) {
                    $this->loggerObj->error("semknoxUpdate: err: CreateArticleDataList: ".$e->getMessage());
                }
                $t2=microtime(true);
                $te=$t2-$t1;
                $logdes=$max.'/'.count($this->articleIdList);
                try {
                    $isError=0;
                    $res=$this->sendAll(1);
                    if ( (!is_array($res)) || ($res['status']!='success') ) {
                        $logdes='Fehler bei BatchUpdateSend'; $st=102;
                        $sql = "INSERT INTO semknox_logs (logtype, status, logdescr) VALUES (?, ?, ?)";
                        $res = Shopware()->DB()->query($sql, array(1, $st, $logdes));
                        $this->cleanUpFiles();
                        $ret['info']='Fehler bei BatchUpdateSend';
                        $this->loggerObj->error("semknoxUpdate: Fehler bei BatchUpdateSend!");
                        return $ret;
                    }
                    $sql = "UPDATE semknox_logs SET logdescr='$logdes' WHERE id=".$this->dbLastinsertID;
                    $resu = Shopware()->Db()->exec($sql);
                    $updateNextPos=$max;$updateNextFile++;
                    file_put_contents('./var/log/semkDBNextPos.js', serialize(array('nextPos'=>$updateNextPos, 'nextFile'=>$updateNextFile)));
                    unlink('./var/log/semkLock.js');
                } catch ( \Exception $e) {
                    $this->loggerObj->error("semknoxUpdate: err: Fehler bei BatchUpdateSend! ".$e->getMessage());
                }
                if ($maxItems>0) { if ($isError) {return $ret; } else { $ret['code']=$max; $ret['info']=$max.'/'.count($this->articleIdList).' gesendet'; return $ret;} }
            }
            if ($max >= count($this->articleIdList)) {
                $res=$this->semknoxBaseApi->finishBatchUpload();
                if ( (!is_array($res)) || ($res['status']!='success') ) { $res='Fehler bei BatchUpdateEnde'; $st=102; $ret['info']='Fehler bei BatchUpdateEnde'; $this->loggerObj->error("semknoxUpdate: Fehler bei BatchUpdateEnde!");} else {
                    $st=101;$logdes=trim($res['resultText']);$ret['code']=0;$ret['info']='Update finished';
                }
                $sql = "INSERT INTO semknox_logs (logtype, status, logdescr) VALUES (?, ?, ?)";
                $res = Shopware()->DB()->query($sql, array(1, $st, $logdes));
                $sql = "DELETE FROM semknox_logs WHERE id=".$this->dbLastinsertID;
                $res = Shopware()->Db()->exec($sql);
                $this->loggerObj->info('semknoxUpdate: '.$res['resultText']);
                $this->cleanUpFiles();
                $this->loggerObj->info("semknoxUpdate: all Data sent to ".$this->config['semknoxBaseUrl']);
                $ret2 = $this->updateCategoryData();
                return $ret;
            }
        }
    private function getLogIDByOrdernumber($ordernumber, $list) {
    	$ret=0;
    	foreach ($list as $a) {
    		if ($a['ordernumber']==$ordernumber) { $ret=$a['id']; break;}
    	}
    	return $ret;
    }
    /**
    aktualisiert einzelne Artikel aus Liste
    */
    public function updateSingle($list) {
    	$olist=array();
    	$res=array();$res['resultCode']=1;
    	foreach($list as $l) {
  			$sql="Update semknox_logs SET status=1 WHERE id = ?";
				$params=array($l['id']);
    		$olist[$l['ordernumber']]=$l['ordernumber'];    		
    	}
    	if (count($olist)<=0) { return; }
    	$itemsDel=0;
    	$itemsUpd=0;
			file_put_contents('./var/log/semkLock.js', "1");
			$sql = "INSERT INTO semknox_logs (logtype, status, logtitle, logdescr) VALUES (1,21,'Einzel-Update Start','Einzuel-Update Start')";
			$res = Shopware()->Db()->exec($sql);  
			$mainLogID = Shopware()->Db()->lastInsertId();
    	$this->getArticleList($olist);
    	$min=0;$max=count($this->articleIdList);
    	$this->createArticleDataList($min, $max);
    	$this->loggerObj->info("semknoxSingleUpdate: articleList: ".count($this->articleList->list)." articleDeleteList: ".count($this->articleDeleteList->list));
    	if (count($this->articleDeleteList->list)>0) {
    		foreach ($this->articleDeleteList->list as $article) {
    			$id=$article->itemData['articleNumber'];
    			if (trim($id)!='') {
    				unset($olist[$id]);
    				$ret=$this->semknoxBaseApi->DeleteArticle($id);
   					$logid=$this->getLogIDByOrdernumber($id, $list);
   					if ($logid > 0) {
	    				if ($ret['status']=='success') {
	    				    $itemsDel++;
	    				    if (!empty($logid)) { 
	    						$sql="DELETE FROM semknox_logs WHERE id = ?";
									$params=array($logid);
									Shopware()->Db()->query($sql, $params);
	    					}
	    				} else {
	    						$sql="Update semknox_logs SET status=102, logdescr=? WHERE id = ?";
									$params=array($ret['status'], $logid);
									Shopware()->Db()->query($sql, $params);    					
	    				}
	    			}
    			}
    		}
    	}
    	if (count($this->articleList->list)>0) {
    		$ret=$this->semknoxBaseApi->UpdateArticleList($this->articleList);
    		if ($ret['resultCode']>0) {
    		    $itemsUpd++;
	    		foreach($this->articleList->list as $it) {
						unset($olist[$it->itemData['articleNumber']]);
	    			$logid=$this->getLogIDByOrdernumber($it->itemData['articleNumber'], $list);
	    			if ($logid > 0) {
	 						$sql="DELETE FROM semknox_logs WHERE id = ?";
							$params=array($logid);
							Shopware()->Db()->query($sql, $params);    				
	    			}
	    		}
	    	} else {
	    		foreach($this->articleList->list as $it) {
						unset($olist[$it->itemData['articleNumber']]);
	    			$logid=$this->getLogIDByOrdernumber($it->itemData['articleNumber'],$list);
	    			if ($logid > 0) {
	    				$sql="Update semknox_logs SET status=102, logdescr=? WHERE id = ?";
							$params=array($ret['resultText'], $logid);
							Shopware()->Db()->query($sql, $params);    				
	    			}
	    		}	    		
	    	}
    	}
    	$this->loggerObj->info("semknoxSingleUpdate: olist: ".count($olist));
			foreach($olist as $id) {
 				$ret=$this->semknoxBaseApi->DeleteArticle($id);
				$logid=$this->getLogIDByOrdernumber($id, $list);
  			if ($logid > 0) {
  			    $itemsDel++;
  			    if ($ret['status']=='success') {
	 					if (!empty($logid)) { 
							$sql="DELETE FROM semknox_logs WHERE id = ?";
							$params=array($logid);
							Shopware()->Db()->query($sql, $params);
	    			}
	    		} else {
	    				$sql="Update semknox_logs SET status=102, logdescr=? WHERE id = ?";
							$params=array($ret['status'], $logid);
							Shopware()->Db()->query($sql, $params);    					
	    		}
	    	}
			}
    	$res=array();$res['resultCode']=1;
    	$sql = "Update semknox_logs SET status='101', logtitle='Einzel-Update', logdescr='$itemsUpd aktualisiert $itemsDel gelöscht' WHERE id='$mainLogID' ";
		$res = Shopware()->Db()->exec($sql);  
    	$this->cleanUpFiles();
    	return $res;    	
    }
    public function update($articleID) {
    }
    private function getCatURL($id, $path) {
        if (trim($path)=='') {
            $ret=$this->domainurl."cat/index/sCategory/".$id;
        } else {
            $ret=$this->domainurl.$path;
        }
        return $ret;
    }
    private function getCatMediaURL($id, $path) {
        $ret='';
        if (empty($id)) { return $ret; }
        $mediao = $this->mediaservice->get($id, $this->contexti);
        if (is_null($mediao)) { return $ret; }
        if ($this->bestThumbNo==-11) {
            $this->getThumbNo($mediao);
        }
        if ($this->bestThumbNo >= 0) {
            if (is_null($mediao->getThumbnails()[$this->bestThumbNo])) {
                $this->getThumbNo($mediao);
            }
            if (!is_null($mediao->getThumbnails()[$this->bestThumbNo])) {
                $ret = $mediao->getThumbnails()[$this->bestThumbNo]->getSource();
            }
        }
        if (trim($ret)=='') {
            $ret=$mediao->getFile();
        }
        $ret=$this->fixShopHost($ret,'local');
        unset($mediao);
        return $ret;
    }
    private function getNormCatPos($path, $position, $maxVal, $maxStages) {
        $ret='';
        $h=substr_count($path, '|') - 1;
        if ($h<=0) { 
            return "0";
        }
        if ($maxVal > 255) {
            return "" . ($position / $maxVal);
        }
        $x=32768;
        if ($h > 1) { $x =  $x >> ($h-1); }
        $x=$x+(255-$position);
        $ret = "". ($x / 65535 );
        return $ret;
    }
    /**
     * fügt dem Titel der übergebenen Kategorie die Titel der übergeordneten Kategorien zu
     * @param array $cat
     * @param array $list
     */
    private function getCatTitle(&$cat, $list) {
        if ($this->config['semknoxUpdateMaxCatParents'] > 0) {
            $plist = explode('|', trim($cat['catPath'], '|'));
            if (count($plist)>2) {
                array_splice($plist, -2);
                if (count($plist)) {
                    $max = intval($this->config['semknoxUpdateMaxCatParents']);
                    if (count($plist) > $max) {
                        array_splice($plist, $max);
                    }
                    $titleList = [];
                    foreach($plist as $parent) {
                        if (isset($list[$parent])) {
                            $titleList[]=$list[$parent]['description'];
                        }
                    }
                    if (count($titleList)>0) {
                        $titleList=array_reverse($titleList);
                        $titleList[] = $cat['description'];
                        $gl = $this->config['semknoxUpdateCatTitleGlue'];
                        if (trim($gl)=='') { $gl=' '; }
                        $cat['description'] = implode($gl, $titleList);
                    }
                }
            }
        }
    }
    private function getCatDatav1() {
        $q = "SELECT c.id, c.description, c.position, c.path as catPath, c.parent as catParent, c.mediaID, (SELECT path FROM s_core_rewrite_urls WHERE org_path = CONCAT('sViewport=cat&sCategory=',c.id) AND main='1' AND subshopID='".$this->shop_id."') as Deeplink FROM s_categories c WHERE c.active > 0";
        $retz = Shopware()->Db()->fetchAll($q);
        $ret=[];$maxVal=0;$maxStages=0;
        $catList=[];
        foreach ($retz as $cat) {
            $h=substr_count($cat['catPath'], '|') - 1;
            if ($h>$maxStages) { $maxStages=$h; }
            if ($cat['position'] > $maxVal) { $maxVal = $cat['position']; }
            $catList[$cat['id']] = $cat;
        }
        foreach ($retz as $cat) {
            if (empty($cat['catParent'])) { continue; }
            if (trim($cat['Deeplink'])=='') { continue; }
            $this->getCatTitle($cat, $catList);
            $a=array('type'=>'CATEGORY', 'categoryId'=>'', 'categoryWeight'=>'', 'imageUrl'=>'', 'url'=>'', 'name'=>'');
            $a['categoryId'] = $cat['id'];
            $a['categoryWeight'] = $this->getNormCatPos($cat['catPath'], $cat['position'], $maxVal, $maxStages);
            $a['name'] = $cat['description'];
            $a['url'] = $this->getCatURL($cat['id'], $cat['Deeplink']);
            $a['imageUrl'] = $this->getCatMediaURL($cat['mediaID'], $cat['mediaPath']);
            $ret[] = $a;
        }
        return $ret;
    }
    private function getCatDatav3() {
        $q = "SELECT c.id, c.description, c.cmstext, c.position, c.path as catPath, c.parent as catParent, c.mediaID, (SELECT path FROM s_core_rewrite_urls WHERE org_path = CONCAT('sViewport=cat&sCategory=',c.id) AND main='1' AND subshopID='".$this->shop_id."') as Deeplink FROM s_categories c WHERE c.active > 0";
        $retz = Shopware()->Db()->fetchAll($q);
        $ret=[];$maxVal=0;$maxStages=0;
        $catList=[];
        foreach ($retz as $cat) {
            $h=substr_count($cat['catPath'], '|') - 1;
            if ($h>$maxStages) { $maxStages=$h; }
            if ($cat['position'] > $maxVal) { $maxVal = $cat['position']; }
            $catList[$cat['id']] = $cat;
        }
        foreach ($retz as $cat) {
            if (empty($cat['catParent'])) { continue; }
            if (trim($cat['Deeplink'])=='') { continue; }
            $this->getCatTitle($cat, $catList);
            $a=array('resultGroup'=>'Kategorien', 'boost'=>'', 'imageUrl'=>'', 'url'=>'', 'title'=>'', 'content'=>'', 'dataPoints'=>[]);
            $d=['key'=> 'categoryId', 'value' => $cat['id'], 'show' => true ];
            $a['dataPoints'][]=$d;
            $a['boost'] = $this->getNormCatPos($cat['catPath'], $cat['position'], $maxVal, $maxStages);
            $a['title'] = $cat['description'];
            $a['url'] = $this->getCatURL($cat['id'], $cat['Deeplink']);
            $a['imageUrl'] = $this->getCatMediaURL($cat['mediaID'], $cat['mediaPath']);
            if (!is_null($cat['cmstext'])) { $a['content'] = $cat['cmstext']; }
            if (!is_null($cat['description'])) { $a['content'] .= " ".$cat['description']; }
            $ret[] = $a;
        }
        return $ret;
    }
    public function updateCategoryData() {
        $this->loggerObj->info("semknoxUpdate: neues CatUpdate");
        $list = $this->getCatDatav3();
        $nlist = Shopware()->Container()->get('events')->filter(
            'SemknoxUpdate_CategoryData_FilterList',
            $list,
            [
                'subject' => $this
            ]
            );
        if (is_array($nlist)) { $list=$nlist; }
        $res=$this->semknoxBaseApi->sendCatDatav3($list); 
        $this->loggerObj->info("semknoxUpdate: CatUpdate beendet");
    }
    public function updateFromMasterFeed() {
    	if (trim($this->config['semknoxMasterFeed'])=='') {    		
    		return;
    	}
    	$this->semknoxBaseApi->UpdateArticlesFromMasterfeed($this->config['semknoxMasterFeed']);
    }
    /**
     * wrapper für API-Call zum Senden der Daten
     * @param number $doBatch = 1 nutze Batch-Modus, sonst Vollupdate
     * @return number|mixed
     */
    private function sendAll($doBatch=0) {
			$res['resultCode']=-19720502;$res['resultText']='NoData';
    	if (count($this->articleList->list)>0) {
    	    if ($doBatch) {
    	        $res=$this->semknoxBaseApi->sendBatchDataBlocks($this->articleList);    	            	        
    	    } else {
    		  $res=$this->semknoxBaseApi->UpdateArticleList($this->articleList, 1);
    	    }
    	    if (trim($this->config['semknoxDebugfile'])!='') {
    	        file_put_contents($this->config['semknoxDebugfile'].'_uploaddata_'.time().'.js', $this->articleList->getJsonList());
    	    }
    	}
    	return $res;
    }
/** wenn der productservice mal als Cronjob läuft...
    private function createArticleDataList() {
    	if (is_null($this->productService)) { return ; }
    	if (count($this->articleIdList)<=0) { return ; }
    	foreach($this->articleIdList as $id) {
    		$data = $this->productService->get($id,$this->context);
    		var_dump($data);exit();
    	}
    }    
*/   
    /**
    *	Transformiert das Artikeldata-Array von Shopware zu semnkoxItemData
    */
    private function transformArticleData ($item) {
			if (trim($item['ArticleNumber'])=="") {return;}
			$a=new semknoxItemData();
			$a->name=$item['Title'];
			$a->articleNumber=$item['ArticleNumber'];
			$a->image=$item['ImageURL'];
			$a->description=$item['Description'];
			$a->category=$item['CategoryPath'];
			$a->secondaryCategories=$item['secondaryCategories'];
			$a->ean=$item['EAN'];
			$a->groupId=$item['GroupId']; 
			$a->appendOnly = false;
			if ( ($this->config['semknoxAppendNoStock']) && ($item['instock']<=0) ) { 
			    $a->appendOnly = true;
			}
			$a->accessories=array(); 
			if (trim($item['Accessories'])!='') {
				$a->accessories=explode(";", $item['Accessories']); 
			}
			$a->addPassOn('shippingTime', $item['ShippingTime']);
			$a->addPassOn('shippingCosts', $item['ShippingCosts']);
			$a->addPassOn('availability', $item['Availability']);
			$a->addPassOn('pseudoprice', $item['PseudoPrice']);
			$a->addPassOn('articleDetailID', $item['MasterArticleNumber']);
			$a->addPassOn('articleID', $item['mainID']);
			if (is_array($item['passons'])) {
				foreach ($item['passons'] as $k => $v){
					$a->addPassOn($k,$v);
				}
			}
			$a->deeplink=$item['Deeplink'];
			$a->price=number_format($item['Price'], 2, ",", "")." ".$item['Currency'];
			$a->manufacturer=$item['Manufacturer'];
			if (is_null($item['Position'])) { $item['Position']=0; }
			if ($this->config['semknoxUseDate']==1) {
			    $a->rankingImportance=floatval(1-(10000000/(floatval($item['Position'])+1)));			    
			} else {
                $a->rankingImportance=floatval(1-(1/(floatval($item['Position'])+1)));
			}
			if ( (floatval($item['rankingImportance'])<0) || (floatval($item['rankingImportance'])>1) ) { $this->loggerObj->error("semknoxUpdate: err: rankingImportance: ".$item['ArticleNumber']);exit(); }
			foreach ($item['Attributes'] as $k => $v) {
			    $a->addAttribute($k, $v);
			}
			return $a;		    
		}
    private function calcValues(&$datalist) {
    	$cmin=2222222222;$cmax=-2222222222;
    	foreach($datalist as $data) {
    		if ($data['datumi'] == 0) { continue; }
    		if ($data['datumi'] < $cmin ) { $cmin = $data['datumi']; }
    		if ($data['datumi'] > $cmax ) { $cmax = $data['datumi']; }    		
    	}
			$crange=$cmax-$cmin;
    	foreach($datalist as &$data) {
    		if ($data['datumi']==0) { $data['datumi']=$cmin; }
    		$data['Position']=(($data['datumi']-$cmin)/$crange);    		
    		unset($data);
    	}
    }
    private function createArticleDataList($min, $max) {
    	if ($min==0) {
    		$this->articleList->clearList();
    		$this->articleDeleteList->clearList();
    	}
    	if (count($this->articleIdList)<=0) { return ; }
    	$datalist=array();
    	$lastid='';
    	$mc=count($this->articleIdList);
    	for ($i=$min; $i < $max; $i++) {
		      if ($i < $mc) {
				$id=$this->articleIdList[$i];
				$x = $this->getArticleData($id);
				if ( (is_array($x)) && (!empty($x)) ) {
  	  		        $datalist[] = $x; 
				}
  	  	      }
    	}
    	foreach($datalist as $data) {
    		if ($this->config['hideNoStock']) {
    			if ( ($data['laststock']) && (intval($data['instock'])<=0) ) { $this->articleDeleteList->addItem($this->transformArticleData($data)); continue; }
    		}
    		if ($this->config['semknoxDeleteNoStock']) {
    		    if (intval($data['instock']) <= 0) { $this->articleDeleteList->addItem($this->transformArticleData($data)); continue; }
    		}
    		$h=$this->articleList->addItem($this->transformArticleData($data));
   		}
    }
    /** auch das erst, wenn die Services laufen...
     * @param int ID des Artikels
     * @return @var \semknoxSearch\Bundle\SearchBundle\semknoxItemData
    private function getArticleData($id){
    	$itemdata=array();
    	$ret = new \semknoxSearch\Bundle\SearchBundle\semknoxItemData ();
    	if (is_null($this->productService)) { return $ret; }
    	$data = $this->productService->get($id);
    	$data = $this->transformArticleData($data);
    	$ret = new \semknoxSearch\Bundle\SearchBundle\semknoxItemData ($data);
    	return $ret;
    }
    */
    private function getMainOrderNumberByMainID($id, $list) {
    	$ret=$id;
    	foreach ($list as $it) {
				if ($it['detailID']==$id) { $ret=$it['ordernumber'];break; } 
    	}
    	return $ret;
    }
    private function getArticleList($list=array()) {
    	$ret=array();
    	$zl='';
    	if ((is_array($list)) && (count($list))) {
    		$zl=' AND d.ordernumber in (';
    		foreach($list as $i) {
    			$zl.="'$i',";
    		}
    		$zl=trim($zl,',');
    		$zl.=') ';
    	} else {
    	    $sqlInstock='d.laststock';
    	    $v=version_compare($this->config['shopwareversion'], '5.4.0', '<');
    	    if ($v) {
    	        $sqlInstock='a.laststock';
    	    }
    		if ($this->config['hideNoStock']) {
    			$zl=" AND ( ($sqlInstock=0) OR (d.instock>0) )";
    		}
    		if ($this->config['semknoxDeleteNoStock']>0) {
    		    $zl=" AND d.instock > 0 ";
    		}
    	}
    	$sql = "SELECT DISTINCT a.id, d.id as detailID, a.main_detail_id, d.ordernumber, (SELECT ordernumber FROM s_articles_details d2 where d2.id = a.main_detail_id) as mainOrdernumber FROM s_articles a, s_articles_details d, s_articles_categories_ro cr WHERE a.active>0 AND d.active>0 AND d.articleID = a.id AND cr.articleID=a.id AND cr.categoryID = '".$this->shopCategory."'  $zl";
      $ret = Shopware()->Db()->fetchAll($sql);  
      $this->articleIdList=$ret;
    }
		function getImgPath($img) {
			$ret='';
	  	if (!empty($img)) {
				$hx=explode('||',$img);
				$imgfile=$hx[0];
				$h=pathinfo($imgfile);
				$imgid=$h['filename'];
				$extension=$h['extension'];			
				$h='media/image/'.$imgid.'.'.$extension;
				$h2=md5($h);
				$h22=str_split($h2,2);
				$newlink=$this->domainurl."media/image/".$h22[0]."/".$h22[1]."/".$h22[2]."/".$imgid.".".$extension;
				$ret=$newlink;
			}		
			return $ret;
		}
		/*
		* sucht im Media-Object eine passende Auflösung Kleinstes Bild >= 300px
		*/
		function getThumbNo($media) {
			if (!is_null($media)) {
				$tl=$media->getThumbnails();
				$aw=10000000;$ap=-1;
				foreach ($tl as $k => $t) {
					$mw=$t->getMaxWidth();
					if ($mw>=300) {
						$h=$mw-300;
						if ($h < $aw) { $aw=$h; $ap=$k; }
					}
				}
				if ($ap>-1) { $this->bestThumbNo=$ap; }
			}
		}
		/**
		 * Funktion gibt zu image-ID die URL zurück
		 * @param string $img
		 * @return string
		 */
		function getImage(string $img) {
			$ret='';
	  	if (!empty($img)) {
				$hx=explode('||',$img);
				if (count($hx)<2) { return $ret; }
				$imgID=trim($hx[1]);
				if ($imgID=='') { return $ret; }
      	$mediao = $this->mediaservice->get($imgID, $this->contexti); 		
      	if (is_null($mediao)) { return $ret; }
      	if ($this->bestThumbNo==-11) {
      		$this->getThumbNo($mediao);
      	}
      	if ($this->bestThumbNo >= 0) {
      		if (is_null($mediao->getThumbnails()[$this->bestThumbNo])) {
      			$this->getThumbNo($mediao);
      		}
      		if (!is_null($mediao->getThumbnails()[$this->bestThumbNo])) {
      			$ret = $mediao->getThumbnails()[$this->bestThumbNo]->getSource();
      		}
      	} 
      	if (trim($ret)=='') {
      		$ret=$mediao->getFile();
      	}
      	$ret=$this->fixShopHost($ret,'local');
      	unset($mediao);      	
      }
      return $ret;
		}
		/**
		 * erhält path der Form 1|2|3 und gibt zu einzelnen IDs die Kategorie-Namen zurück
		 * @param string $path
		 */
		function getCatNamesByPath($path) {
		    $h=explode('|',$path);$list='';
		    foreach($h as $i) {
		        $cid=(int) $i;
		        if ((trim($i)!='')) {
		            $sql="SELECT c.description  FROM s_categories c WHERE c.id='$cid'  ";
		            $res = Shopware()->Db()->fetchAll($sql);
		            if (empty($res)) { continue; }
		            $res=$res[0];
		            $list=str_replace('|','&#124;',$res['description']).$this->KatPathDelimiter.$list;
		        }
		    }		
		    return $list;
		}
	/**
	holt zu Artikelid id zugehörigen Kategorien vom ersten gefundenen Path
	*/
		function getKatPath($id, &$product, &$secCategoriesList) {
		    if ($this->config['semknoxUseOnlySEOCats']) {
		        $sql = "SELECT ac.category_id as categoryID, c.path,
    			ROUND (
    			    (
    			        LENGTH(path)
    			        - LENGTH( REPLACE ( path, \"|\", \"\") )
    			        ) / LENGTH(\"|\")
    			    ) AS anzahl
    			FROM  s_categories c, s_articles_categories_seo ac WHERE article_id = '$id' AND c.id=ac.category_id AND ac.shop_id = '".$this->shop_id."' ORDER BY anzahl DESC";		        
		    } else {
    		    $sql = "SELECT ac.categoryID, c.path,
    			ROUND (
    			    (
    			        LENGTH(path)
    			        - LENGTH( REPLACE ( path, \"|\", \"\") )
    			        ) / LENGTH(\"|\")
    			    ) AS anzahl
    			FROM  s_categories c, s_articles_categories ac WHERE articleID = '$id' AND c.id=ac.categoryID AND c.path like '%|".$this->shopCategory."|%' ORDER BY anzahl DESC";
		    }
		    $res = Shopware()->Db()->fetchAll($sql);
		    if (empty($res)) { return ""; }
		    $ret='';$secCategoriesList=array();
		    foreach ($res as $resu) {
		        $katlist=array();
		        $product=$resu['categoryID'];
		        $itempath=$product.$this->KatPathDelimiter.$resu['path'];
		        $h=trim($this->getCatNamesByPath($itempath));
		        if ($h=='') { continue; }
		        $j.='|';
		        $h=trim($h,$this->KatPathDelimiter);
		        if (trim($ret)=='') { $ret=$h; } else {
		            if (in_array($h,$secCategoriesList)) { continue; }
		            if ($h==$ret) { continue; }
		            $secCategoriesList[]=$h;
		        }
		    }
		    return $ret;
		}
		function getAttributes($id) {
			$ret=array();
			$sql="select o.name, v.value, o.filterable from s_filter_articles a, s_filter_values v, s_filter_options o  where a.articleID=$id and v.id=a.valueID and o.id=v.optionID";
			$res = Shopware()->Db()->fetchAll($sql);    			
			if (empty($res)) { return $ret; }
			foreach ($res as $item) {
				$ret[$item['name']]=$item['value'];
			}
			return $ret;
		}
		/**
		 * ergänzt die Liste der Merkmale um die Shopware-Attribute. 
		 * Nur Freitext-Attribute werden übernommen.
		 * @param int $mainID
		 * @param int $detailID
		 * @param string $ret
		 * @return string
		 */
		function setShopAttributes($mainID, $detailID, &$ret) {
		    if ($this->defAttributes==0) {
		        $this->defAttributes=array();
		        $sql="SELECT column_name, column_type, label FROM s_attribute_configuration WHERE table_name = 's_articles_attributes' ";
		        $res = Shopware()->Db()->fetchAll($sql);
		        if (empty($res)) { return $ret; }
		        foreach ($res as $item) {
		            $this->defAttributes[$item['column_name']]=$item;
		        }		        
		    }
		    if ( (!is_array($this->defAttributes)) || (count($this->defAttributes)<=0) ) { return $ret; }
		    $sql="select * from s_articles_attributes a  where a.articleID=$mainID AND a.articledetailsID=$detailID";
		    $res = Shopware()->Db()->fetchAll($sql);
		    if (empty($res)) { return $ret; }
		    foreach ($res as $item) {
		        foreach ($this->defAttributes as $k => $v) {
		            if ( (isset($item[$k])) && (!empty($item[$k])) )  {
		              $ret[$v['label']]=$item[$k];
		            }
		        }
		    }
		}
		function getVariantConfig($id, &$attribs, &$passons) {
			$sql="select g.name, o.name as value, o.id from s_article_configurator_option_relations r, s_article_configurator_options o, s_article_configurator_groups g where r.article_id = $id  AND r.option_id = o.id  AND o.group_id = g.id";
			$res = Shopware()->Db()->fetchAll($sql);    			
			if (empty($res)) { return $ret; }
			foreach ($res as $item) {
				$attribs[$item['name']]=$item['value'];
				$passons['_variant#'.$item['name']]=$item['value'];
			}
		}
		function getAccessories($code) {
			$ret="";
			$sql="select d.ordernumber from s_articles a, s_articles_details d, s_articles_relationships r where  r.articleID = (SELECT d.articleID FROM s_articles_details d where d.ordernumber='$code') AND d.articleID=r.relatedarticle AND d.articleID = a.id";
   		$res = Shopware()->Db()->fetchAll($sql);    					
   		if (empty($res)) { return $ret; }
			foreach($res as $item) {
				if ($ret!='') { $ret.=';';}
				$ret.=$item['ordernumber'];
			}
			return $ret;
		}
		function getManufacturer($manu) {
			$ret=$manu;
			return $ret;
			$allowedsuppliers = array(mb_strtolower('ZIEGLER','UTF-8'), mb_strtolower('city design','UTF-8'), mb_strtolower('mmcité','UTF-8'), mb_strtolower('biohort','UTF-8'), mb_strtolower('Calzolari','UTF-8'));
			$m=mb_strtolower($manu,'UTF-8');
			if (!in_array($m,$allowedsuppliers)) { $ret='ZIEGLER'; }
			return $ret;
		}	
		private function getCustomerGroupInfo($cmDef) {
			$q="SELECT * FROM s_core_customergroups ORDER BY ID";
			$res = Shopware()->Db()->fetchAll($q);    
			$fi='';$fi2='';
			foreach($res as $it) { 
				if ($fi=='') { $fi=$it; }
				if ($it['groupkey']==$cmDef) { $fi2=$it; } 
			}
			if ($fi2!='') { $fi=$fi2; }
			if ($fi['tax']) { $this->sendBruttoPrice=1; } else { $this->sendBruttoPrice=0; }
			$this->customerGroup=$fi['groupkey'];
		}
		function getGUID(){
		    if (function_exists('com_create_guid')){
		        return com_create_guid();
		    }
		    else {
		        mt_srand((double)microtime()*10000); 
		        $charid = strtoupper(md5(uniqid(rand(), true)));
		        $hyphen = chr(45); 
		        $uuid = chr(123) 
		            .substr($charid, 0, 8).$hyphen
		            .substr($charid, 8, 4).$hyphen
		            .substr($charid,12, 4).$hyphen
		            .substr($charid,16, 4).$hyphen
		            .substr($charid,20,12)
		            .chr(125); 
		        return $uuid;
		    }
		}
		/**
		generiert die Artikel-liste mit Basisdaten
		*/
    private function getArticleData($idset, $cg='EK') {
			if ($this->customerGroup=='') {
				$this->getCustomerGroupInfo($cg);
			}
			$usergroup=$this->customerGroup;
			$mainID=$idset['id'];
			$detailID=$idset['detailID'];
			$sqlInstock='d.laststock';
			$v=version_compare($this->config['shopwareversion'], '5.4.0', '<');
			if ($v) {
			    $sqlInstock='a.laststock';
			}
			$q="SELECT d.ordernumber as ArticleNumber, d.id as MasterArticleNumber, a.name as Title, a.description_long as Description, '' as DescriptionPlain, a.description as ShortDescription, 
				(SELECT path FROM s_core_rewrite_urls WHERE org_path = CONCAT('sViewport=detail&sArticle=',a.id) AND main='1' AND subshopID='1') as Deeplink,
				(SELECT CONCAT(i.img,'.',i.extension,'||',i.media_id) from s_articles_img i WHERE i.articleID=d.articleID AND i.main='1'  AND i.article_detail_id IS NULL ORDER BY i.position LIMIT 1) as ImageURL,
				(SELECT price FROM s_articles_prices p, s_articles_details WHERE s_articles_details.id=p.articledetailsID AND pricegroup='$usergroup' AND s_articles_details.articleID=d.articleID GROUP BY ROUND(price,2) ORDER BY price ASC LIMIT 1) as Price,
				\"€\" as Currency,
				(SELECT name from s_articles_supplier s WHERE s.id=a.supplierID) as Manufacturer
				,'' as Attributes, '' as CategoryPath, ".$this->config['semknoxRankingAttribute']." as Position, d.shippingtime as ShippingTime, '0' as ShippingCosts, \"Auf Lager\" as Availability,
				(SELECT pseudoprice from s_articles_prices p WHERE p.articleID=d.articleID AND p.articleDetailsID=d.id AND pricegroup='EK' AND p.`from`='1' ORDER BY p.from LIMIT 1) as PseudoPrice
				,d.ean as EAN
				,d.width, d.height, d.weight, d.length
				,a.datum
				,IFNULL((SELECT CONCAT(ROUND(AVG(points), 2), '|', COUNT(*)) as votes FROM s_articles_vote v WHERE v.active=1 AND v.articleID=a.id), '0.00|0') as vote
				,aa.*							
				,(SELECT baseprice FROM s_articles_prices p, s_articles_details WHERE s_articles_details.id=p.articledetailsID AND pricegroup='$usergroup' AND s_articles_details.articleID=d.articleID GROUP BY ROUND(price,2) ORDER BY price ASC LIMIT 1) as basePrice
				,(SELECT SUM(impressions)  FROM s_statistics_article_impression ai WHERE ai.articleID=a.id) as impressions
				,d.sales, d.instock, a.datum as releasedate, $sqlInstock, d.suppliernumber as herstellernummer, a.metaTitle, a.keywords,
				(SELECT ta.tax FROM s_core_tax ta where ta.id=a.taxID) as tax,
                (select CONCAT(image.img,'.',image.extension,'||',image.media_id) from s_articles_img image INNER JOIN s_articles_img childImage ON childImage.parent_id = image.id WHERE childImage.article_detail_id = $detailID ORDER BY image.position ASC LIMIT 1) as DetailImageURL
		  	FROM  s_articles a, s_articles_details d LEFT JOIN  s_articles_attributes aa ON aa.articleID = d.articleID AND aa.articledetailsID = d.id
		  	WHERE a.id=d.articleID AND d.id=$detailID AND a.id=$mainID";
            $ret = Shopware()->Db()->fetchAll($q);    
            if (empty($ret)) { return $ret; }
            $ret=$ret[0];
            $updateArticle = true;
            $updateArticle = Shopware()->Container()->get('events')->filter(
                'SemknoxUpdate_ArticleData_doUpdate',
                $updateArticle,
                [
                    'subject' => $this,
                    'itemNumber' => $ret['ArticleNumber'],
                    'ret' => $ret,
                ]
                );
            if ($updateArticle === false) {
                return null;
            }
			$ret['Deeplink']=$this->domainurl.$ret['Deeplink'];
			if ($idset['main_detail_id']!=$detailID) {
				$ret['Deeplink'].="?number=".$ret['ArticleNumber'];
			}			
			$imgbak=$ret['ImageURL'];$af=0;
			if (trim($ret['DetailImageURL'])!='')
			{
			    $af=1;
			    $ret['ImageURL']=$this->getImage($ret['DetailImageURL']);
			    if (trim($ret['ImageURL'])=='') { $ret['ImageURL']=$imgbak; $af=0; }
			} 
			if ( (trim($ret['ImageURL'])!='') ) 
			{
			    if ($af==0) {
			     $ret['ImageURL']=$this->getImage($ret['ImageURL']);
			    }
			} else {
			    $ret['ImageURL']='';
			}
			if ( ($this->sendBruttoPrice) && ($ret['Price']>0) && ($ret['tax']>0) )	{ $ret['Price']=round($ret['Price']*(1+($ret['tax']/100)),2); }
			if (empty($ret['Description'])) { $ret['Description']=""; }			
			$ret['DescriptionPlain']=strip_tags($ret['Description'],'<br>');
			$ret['DescriptionPlain']=str_ireplace('<br>', ' <br> ',$ret['DescriptionPlain']);
			$ret['DescriptionPlain']=str_ireplace('<br/>', ' <br/> ',$ret['DescriptionPlain']);
			$ret['Zustand']='neu'; 
			$r=array();foreach($exportfields as $f) { $r[$f]=''; }
			foreach($ret as $k => $v) { $r[$k]=$v;}
			$ret=$r;		
			$ret['firstCat']='';$ret['secondaryCategories']=array();
			$ret['CategoryPath']=$this->getKatPath($mainID, $ret['firstCat'], $ret['secondaryCategories']);
			$semknoxGroupId = '';
			$semknoxGroupId = Shopware()->Container()->get('events')->filter(
			    'SemknoxUpdate_ArticleData_GroupId',
			    $semknoxGroupId,
			    [
			        'subject' => $this,
			        'idset' => $idset,
			        'ret' => $ret,
			    ]
			    );
			if (!empty(trim($semknoxGroupId))) {
			    $ret['GroupId'] = $semknoxGroupId;
			} else {
    			switch ($this->config['semknoxGroupID']) {
    				case 1	: $ret['GroupId']=$idset['mainOrdernumber'];break;
    				case 2	: $ret['GroupId']=$ret['firstCat'];break;
    				default : $ret['GroupId']='';
    			}
			}
			if (trim($ret['GroupId']) === '') { $ret['GroupId']=$this->getGUID(); }
			$ret['Accessories']=$this->getAccessories($ret['ArticleNumber']);
			$ret['Manufacturer']=$this->getManufacturer($ret['Manufacturer']);
			$ret['Attributes']=$this->getAttributes($mainID);
			$this->setShopAttributes($mainID, $detailID, $ret['Attributes']);
			$ret['passons']=array();
			$this->getVariantConfig($detailID,$ret['Attributes'],$ret['passons']);
			$ret['passons']['link']=$ret['Deeplink'];
			$ret['passons']['mainOrdernumber']=$idset['mainOrdernumber'];
			$ret['Attributes']['width']=$ret['width'];
			$ret['Attributes']['height']=$ret['height'];
			$ret['Attributes']['weight']=$ret['weight'];
			$ret['Attributes']['length']=$ret['length'];
			$hs=explode("|",$ret['vote']);
			$ret['Attributes']['votingValue']=$hs[0];
			$ret['Attributes']['votingCount']=$hs[1];
			if ($ret['instock']>0) { $ret['Availability'] = 'auf Lager'.";"; } else { $ret['Availability']='nicht auf Lager'.";"; }
			$ret['Attributes']['availability']=$ret['Availability'];
			$ret['Attributes']['shippingTime']=$ret['ShippingTime'];
			$ret['Attributes']['shippingCosts']=$ret['ShippingCosts'];
			$ret['Attributes']['pseudePrice']=$ret['PseudoPrice'];
			$ret['Attributes']['salesCount']=$ret['sales'];
			$ret['Attributes']['inStock']=$ret['instock'];
			$ret['marge']=0;
			if ($ret['basePrice']) { $ret['marge']=$ret['Price']-$ret['basePrice']; }
			$ret['Attributes']['marge']=$ret['marge'];
			$ret['Attributes']['articleImpressions']=$ret['impressions'];
			$ret['Attributes']['releaseDate']=$ret['releasedate'];
			$ret['Attributes']['supplierNumber']=$ret['herstellernummer'];
			$ret['Attributes']['metaTitle']=$ret['metaTitle'];
			$ret['Attributes']['keywords']=$ret['keywords'];
			$ret['mainID']=$mainID;
			$ret['datumi']=strtotime($ret['datum']);			
			if (is_null($ret['datumi'])) { $ret['datumi']=0; }
			return $ret;
		}
		/**
		 * Makes sure the given URL contains the correct host for the selected (sub-)shop
		 *
		 * @param string $url
		 * @param string $adapterType
		 * @return string
		 */
		private function fixShopHost($url, $adapterType)
		{
		    if ($adapterType !== 'local') {
		        return $url;
		    }
		    $changed = Shopware()->Container()->get('events')->filter(
		        'SemknoxUpdate_fixImageHost',
		        $url,
		        [
		            'subject' => $this
		        ]
		        );
		    if ( (isset($changed)) && (is_array($changed)) && (isset($changed['changed'])) && isset($changed['url']) ) {
		        if ($changed['changed']) {
		            return $changed['url'];
		        } else {
		            return $url;
		        }
		    }
		    $url = str_replace(parse_url($url, PHP_URL_HOST), $this->shopData['host'], $url);
		    if ($this->shopData['secure']) {
		        return str_replace('http:', 'https:', $url);
		    }
		    return $url;
		}
		/**
		 * @param $id
		 *
		 * @return mixed
		 */
		private function getShopData($id)
		{
		    static $cache = [];
		    if (isset($cache[$id])) {
		        return $cache[$id];
		    }
		    if (empty($id)) {
		        $sql = 's.`default`=1';
		    } elseif (is_numeric($id)) {
		        $sql = 's.id=' . $id;
		    } elseif (is_string($id)) {
		        $sql = 's.name=' . Shopware()->Db()->quote(trim($id));
		    }
		    $cache[$id] = Shopware()->Db()->fetchRow("
            SELECT
              s.id,
              s.main_id,
              s.name,
              s.title,
              COALESCE (s.host, m.host) AS host,
              COALESCE (s.base_path, m.base_path) AS base_path,
              COALESCE (s.base_url, m.base_url) AS base_url,
              COALESCE (s.hosts, m.hosts) AS hosts,
              GREATEST (COALESCE (s.secure, 0), COALESCE (m.secure, 0)) AS secure,
              COALESCE (s.template_id, m.template_id) AS template_id,
              COALESCE (s.document_template_id, m.document_template_id) AS document_template_id,
              s.category_id,
              s.currency_id,
              s.customer_group_id,
              s.fallback_id,
              s.customer_scope,
              s.`default`,
              s.active
            FROM s_core_shops s
            LEFT JOIN s_core_shops m
              ON m.id=s.main_id
              OR (s.main_id IS NULL AND m.id=s.id)
            LEFT JOIN s_core_shop_currencies d
              ON d.shop_id=m.id
            WHERE s.active = 1 AND $sql
            GROUP BY s.id
        ");
/*
 * v. >5.4 no secure-Data in config, only if ssl or not!		   
		    GREATEST (COALESCE (s.always_secure, 0), COALESCE (m.always_secure, 0)) AS always_secure,
		    COALESCE (s.secure_host, m.secure_host) AS secure_host,
		    COALESCE (s.secure_base_path, m.secure_base_path) AS secure_base_path,
*/		    
		    return $cache[$id];
		}
}
?>
