<?php
/**
* @category   WolkeSoftware
* @package    Shopware_Plugins
* @subpackage Plugin
* @copyright  Copyright (c) 2018, WolkeSoftware
* @version    $Id$
* @author     WolkeSoftware
std-id: 476
std-key: zw235dh5h9m7pc04h3fwgys8q1hb86z4
*/
namespace semknoxSearch;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Doctrine\ORM\Tools\SchemaTool;
use semknoxSearch\Bundle\SearchBundle\wosoProductNumberSearch;
use semknoxSearch\bin\semknoxUpdate;
use semknoxSearch\Models\semknoxLogTable;
use semknoxSearch\bin\wosoLogger; 
class semknoxSearch extends \Shopware\Components\Plugin{
	private $aktWosoSearch = null;
	private $config = array();
	private $loggerObj = null;
public function install(InstallContext $context)
{
	/*
	$element = Shopware()->Models()->getRepository(Element::class)->findOneBy(['name' => 'semknoxUseHeadOnly']);
  $element->setOptions(['store' => [
            [1, "LOL"],
            [2, "LOL2"]
  ]]);
  Shopware()->Models()->persist($element);
  Shopware()->Models()->flush();
*/
	$this->createSchema();
/*	
	$this->createCronJob(
			'Semknox complete update',
			'SemknoxUpdateDataJob',
			86400,
			true
	);
	$this->createCronJob(
			'Semknox update single articles',
			'SemknoxUpdateSingleDataJob',
			300,
			true
	);
*/
	if (! Shopware()->Acl()->hasResourceInDatabase('semknoxsearchbackendmodule')) {
  	Shopware()->Acl()->createResource(
      'semknoxsearchbackendmodule',
      ['read'],
      'semknoxSearch',
      $this->getPluginId()
  	);
  }
  parent::install($context);
	return true;
}
public function update(UpdateContext $context)
{
        return true;
}
public function activate(ActivateContext $context)
{
}
public function deactivate(DeactivateContext $context)
{
}
public function uninstall(UninstallContext $context)
{
$this->removeSchema();
parent::uninstall($context);   
}	
public function afterInit()
{
	$this->getConfig();
	parent::afterInit(); 
}    
    public function onRouteStartup(\Enlight_Controller_EventArgs $args)
    {
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
    private function getConfig() {
    		if (!empty($this->config)) { return; }
    		$this->config=array();
    		$this->config['semknoxActivate'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxActivate'),0);
    		$this->config['semknoxBaseUrl'] = "https://api-shopware.sitesearch360.com/";
    		$this->config['semknoxCustomerId'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxCustomerId'));
    		$this->config['semknoxApiKey'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxApiKey'));
    		$this->config['semknoxRewriteCat'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxRewriteCat'),0);
    		$this->config['semknoxUseGrouped'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxUseGrouped'),0);     		
     		$this->config['semknoxGroupID'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxGroupID'),0);
    		$this->config['semknoxUseHeadOnly'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxUseHeadOnly'),1);
    		$this->config['semknoxUseVariantConfigAsFlag'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxUseVariantConfigAsFlag'),0);    		
    		$this->config['semknoxRankingAttribute'] = Shopware()->Config()->getByNamespace('semknoxSearch','semknoxRankingAttribute');
    		$this->config['semknoxRedirCode'] = Shopware()->Config()->getByNamespace('semknoxSearch','semknoxRedirCode');
    		if (trim($this->config['semknoxRedirCode'])=='') { $this->config['semknoxRedirCode']='302'; }
    		$this->config['semknoxRedirCode']=intval($this->config['semknoxRedirCode']);
    		if (($this->config['semknoxRedirCode']<200) || ($this->config['semknoxRedirCode']>600)) { $this->config['semknoxRedirCode']=302; }
     		$this->config['semknoxUpdateTemplates'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxUpdateTemplates'),0);
    		$this->config['semknoxUpdateSingle'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxUpdateSingle'),0);
    		$this->config['semknoxUpdateMaxItems']=intval(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxUpdateMaxItems')); if ($this->config['semknoxUpdateMaxItems']<=0) { $this->config['semknoxUpdateMaxItems'] = 5000; }
    		if (trim($this->config['semknoxRankingAttribute'])=='') { $this->config['semknoxRankingAttribute']='UNIX_TIMESTAMP(d.releaseDate)'; }  		
    		$this->config['semknoxUseAutosug'] = 1;
    		$this->config['semknoxUseFallback'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxUseFallback'),1); 
    		$this->config['semknoxAddOrdernumber'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxAddOrdernumber'),1);
    		$this->config['semknoxAppendNoStock'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxAppendNoStock'),0);
    		$this->config['semknoxDeleteNoStock'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxDeleteNoStock'),0);
    		$this->config['semknoxPreferPrimaryVariant'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxPreferPrimaryVariant'),0);
    		$this->config['hideNoStock'] = Shopware()->Config()->get('hideNoInStock');
    		$this->config['shopwareversion'] = Shopware()->Config()->get('version');
    		$this->config['semknoxRegEx'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxRegEx'));
    		$this->config['semknoxRegExRepl'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxRegExRepl'));
    		$this->config['semknoxDebugfile'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxDebugfile'));
    }
public static function getSubscribedEvents()
{
return [
'Enlight_Controller_Front_RouteStartup' => 'onRouteStartup',
'Enlight_Bootstrap_AfterInitResource_shopware_search.product_number_search' => 'registerProductNumberSearch',
'Shopware_CronJob_SemknoxUpdateDataJob' => 'onRunSemknoxUpdateCronjob',
'Shopware_CronJob_SemknoxUpdateSingleDataJob' => 'onRunSemknoxUpdateSingleCronjob',
'Enlight_Controller_Dispatcher_ControllerPath_Backend_semknoxSearchBackendModule' => 'onGetBackendController',
'Shopware_Controllers_Frontend_Search::defaultSearchAction::after'=>'searchmodify',
'Enlight_Controller_Action_PostDispatchSecure_Frontend_AjaxSearch' => 'onPostSearch',
'Enlight_Controller_Action_PostDispatchSecure_Frontend_Search' => 'onPostSearch',
'Legacy_Struct_Converter_Convert_List_Product' => 'onProductConvert',
'Theme_Compiler_Collect_Plugin_Less' => 'addLessFiles',
'Enlight_Controller_Action_PostDispatch_Backend_Article' => 'onPostDispatchArticle',
'Shopware\Models\Article\Article::postPersist' => 'onAfterArticleUpdate',
'Shopware\Models\Article\Article::postUpdate' => 'onAfterArticleUpdate',
'Shopware\Models\Article\Detail::postPersist' => 'onAfterArticleDetailUpdate',
'Shopware\Models\Article\Detail::postUpdate' => 'onAfterArticleDetailUpdate',
'Shopware\Models\Article\Article::preRemove' => 'onAfterArticleUpdate',
'Shopware\Models\Article\Article::postRemove' => 'onAfterArticleUpdate',
'product_stock_was_changed' => 'onStockChange',
''
];
}
    /**
     * creates database tables on base of doctrine models
     */
    private function createSchema()
    {
        $tool = new SchemaTool($this->container->get('models'));
        $tables = [
            'semknox_logs' => $this->container->get('models')->getClassMetadata(semknoxLogTable::class)
        ];
		    $schemaManager = Shopware()->Container()->get('models')->getConnection()->getSchemaManager();
		    foreach($tables as $tableName => $class){
		      if (!$schemaManager->tablesExist($tableName)) {
		          $tool->createSchema([$class]);
		      }else{
		          $tool->updateSchema([$class], true); 
		      }
		    }
    }
    private function removeSchema()
    {
        $tool = new SchemaTool($this->container->get('models'));
        $classes = [
            $this->container->get('models')->getClassMetadata(semknoxLogTable::class)
        ];
        $tool->dropSchema($classes);
   	}
public function registerProductNumberSearch() {	
	/** Service decorate */	
	if (!$this->checkLoginCreds()) return; 
	if ($this->selcontroller=="emotion") return;
	$coreService  = Shopware()->Container()->get('shopware_search.product_number_search');
	$methods = get_class_methods (coreService);    	  
	$vars = get_class_vars(coreService);
	$wosoSearch = new wosoProductNumberSearch($coreService);
	Shopware()->Container()->set('shopware_search.product_number_search', $wosoSearch);
	/**/      
	/** Service replace::
	return new wosoSearch();
	/**/
}
    /**
     * Create cron job method
     * @param string $name
     * @param string $action
     * @param int $interval
     * @param int $active
     */
    public function createCronJob($name, $action, $interval = 86400, $active = 1)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $connection->insert(
            's_crontab',
            [
                'name'       => $name,
                'action'     => $action,
                'next'       => new \DateTime(),
                'start'      => null,
                '`interval`' => $interval,
                'active'     => $active,
                'end'        => new \DateTime(),
                'pluginID'   => $this->getPluginId(),
            ],
            [
                'next' => 'datetime',
                'end'  => 'datetime',
            ]
        );
    }
    public function getPluginId() {
    	$params=array("");
    	$query="SELECT * FROM s_core_plugins WHERE name like 'semknoxSearch' ";
    	$dbResult = Shopware()->Db()->fetchOne($query,$params);
    	return $dbResult;
    }
public function onRunSemknoxUpdateCronjob( \Shopware_Components_Cron_CronJob $job) {
    $logResult='';
    if (is_null($this->loggerObj)) { $this->loggerObj = new wosoLogger(); }
	$this->getConfig();
	$aktdate=time();$lastdate=$aktdate-30000;
	$f=file_get_contents('./var/log/semkLastCron.txt');
	if ($f) {
   	    $lastdate=unserialize($f);
    }
    $lastdate=0;
    if (($aktdate-$lastdate) < 21000) {
        $logResult="SemknoxUpdate: Zeit zwischen Vollupdates zu kurz! Bitte Cronjob anpassen > 6h (".($aktdate-$lastdate).")";
        $this->loggerObj->error("SemknoxUpdate: Zeit zwischen Vollupdates zu kurz! Bitte Cronjob anpassen > 6h (".number_format(($aktdate-$lastdate)/3600,2).")");
        $this->loggerObj->info("SemknoxUpdate beendet");
    }
    $shopIdList=$this->getsemkShopIDList();
    foreach ($shopIdList as $shopId) {
    	$update = new semknoxUpdate($shopId);
    	if ($update->error < -1000) { continue; }
    	$ret=$update->updateAllBatch($this->config['semknoxUpdateMaxItems'],1,1);
    	$run=0;
    	while ( (is_array($ret)) && ($ret['code']>0) ) {
    	    unset($update);
    	    $update = new semknoxUpdate($shopId);
    	    $ret=$update->updateAllBatch($this->config['semknoxUpdateMaxItems'],0,1);
    	    $run++;
    	}
    }
	if ($run) { 	file_put_contents('./var/log/semkLastCron.txt', serialize($aktdate));	}
	$this->loggerObj->info("SemknoxUpdate beendet");
	$logResult="SemknoxUpdate beendet";
}
/**
 * Einzelupdate für Artikel
 * Aktualisiert werden die geänderten Artikel für alle Shops, da im Backend Änderungen nicht auf Shop-Basis geführt werden können
 * @param \Shopware_Components_Cron_CronJob $job
 * @return string
 */
public function onRunSemknoxUpdateSingleCronjob( \Shopware_Components_Cron_CronJob $job) {        
    $shopIdList=$this->getsemkShopIDList();
    $list=0;
    foreach ($shopIdList as $shopId) {
    	$update = new semknoxUpdate($shopId);
    	if ($update->error < -1000) { continue; }
    	if ($update->isUpdateRunning()>0) { /*return 'update already running';*/ }
    	if ($list==0) {
        	$sql = "SELECT * FROM semknox_logs WHERE logtype=111 AND status=0";
        	$params=array();
        	$results = Shopware()->Db()->fetchAll($sql,$params);  		
        	$list=array();
        	foreach($results as $r) {
        		$r['ordernumber']=$r['logtitle'];
        		$list[]=$r;
        	}
    	}
    	$update->updateSingle($list);
    }
}
    /**
     * @return string
     */
    public function onGetBackendController()
    {
        return __DIR__ . '/Controllers/Backend/semknoxSearchBackendModule.php';
    }
    public function searchmodify(\Enlight_Hook_HookArgs $args) 
    {
    	return;
        $controller = $args->getSubject();
        if (Shopware()->Shop()->getTemplate()->getVersion() >= 3) {
            $controller->View()->addTemplateDir(__DIR__ . '/Resources/views/');
        }
  	}
    private function getSpecPasson($typ, $key, $val) {
    	$ret=array();
    	$ht='_'.$typ;
    	if (mb_substr($key,0, strlen($ht)) == $ht) {
    		$h=explode('#',$key);
    		if (count($h)==2) {
    			$ret['value']=$val;
    			$ret['viewName']=$h[1];
    			$ret['typ']=$typ;
    			$ret['id']=mb_substr($h[0], strlen($ht));
    		}
    	}
    	return $ret;
    }
    private function addSemkAttribsToArticle(&$item) {
    	if ( (!is_array($item['attributes'])) || (!is_object($sa=$item['attributes']['search'])) ) { return; }
			$showConfigFlags=$this->config['semknoxUseVariantConfigAsFlag'];
			$sa=$item['attributes']['search']; 
			$artNo=$sa->get('articleNumber');
			if (empty($artNo)) { return; }
			$po=$sa->get('passOn');$passon=array();
			foreach($po as $it) { $passon[$it['key']]=$it['value']; } 
			$flags=$sa->get('flags');
			if ($this->config['semknoxAddOrdernumber']) {
			  $item['linkDetails'].='&number='.$item['ordernumber'];
			}
			$hdesc='';
			foreach($passon as $k => $v) {
					$h=$this->getSpecPasson('cop', $k, $v);
					if ( (is_array($h)) && (count($h)>0) ) {
						if (strlen($hdesc)) { $hdesc.=', '; }
						$hdesc.=$h['viewName'].': '.$h['value'];
						if ($showConfigFlags) {
							$flags[]=$h;
						}
					}
			}
			$alt=$sa->get('altarticles');$altarticles=array();
			if (is_array($alt)) {
				foreach($alt as $alta) {
					$altarticles[]=$alta['articleNumber'];
				}
			}
			foreach($flags as &$flag) {
				$flag['isPrice']=0;
				if (strtolower($flag['viewName'])=='preis') {
					$flag['value'] = $item['price'];
					$flag['isPrice']=1;
				}
				unset($flag);
			}
			$item['articleName2']=$hdesc;
			$item['semknoxSearch']=array(
			'passOn'=>$passon,
			'flags'=>$flags,
			'altarticles'=>$altarticles
			);	
    }
    public function onProductConvert (\Enlight_Event_EventArgs $args)  {
				$this->getConfig();
				$n=$args->getReturn();    
				$this->addSemkAttribsToArticle($n);
				$args->setReturn($n);
    }
    private function showDebugTimes($tl) {
        foreach ($tl as $k => $vl) {
            echo "\n\n<br /><b>".$k."</b>\n";
            $pt=0;
            foreach ($vl as $v) {
                if ($pt==0) { $pt=$v[0]; }
                $x=($v[0]-$pt);
                if ($k=='curl_data') {
                    $x=$v[0];
                }
                echo "\n<br />".$x." - ".$v[1];
            }
        }
    }
    private function getDebugTimes($tl) {
        $ret='';
        foreach ($tl as $k => $vl) {
            $pt=0;
            foreach ($vl as $v) {
                if ($pt==0) { $pt=$v[0]; }
                if ($k=='curl_data') {
                    $xv=$v[0];
                    $x=$v[1];
                } else {
                    $h=substr($v[1], 0,5);
                    if ($h=='start') {
                        $f=0;$x=substr($v[1],5,1000);
                        for ($i=0;$i<count($vl); $i++) { 
                            if($vl[$i][1]=='stop'.substr($v[1],5,1000)) {
                                $f=$vl[$i][0];break;                                
                            }
                        }
                        if ($f > 0) {
                            $xv=$f-$v[0];
                        }
                    } else { continue; }
                }
                $ret.= "\n".$k.'__'.$x." : ".number_format($xv,5)."s";
            }
        }
        return $ret;
    }
    public function onPostSearch(\Enlight_Event_EventArgs $args)  {
		if (!$this->checkLoginCreds()) return; 
		$this->getConfig();
		$controller = $args->getSubject();	
		$response = $controller->Response();
		$request = $controller->Request();
        $view = $args->getSubject()->View();
        $searchNumberService = Shopware()->Container()->get('shopware_search.product_number_search');
        if ( (isset($searchNumberService)) && (isset($searchNumberService->semknoxAttributes)) ) {
            $controller->View()->assign('semknoxSearchResults', $searchNumberService->semknoxAttributes);
        }
        if ($searchNumberService->semknoxSearchSuccess==0) { return; }
        if (trim($this->config['semknoxDebugfile'])!='') { 
            $t=$this->getDebugTimes($searchNumberService->debugTimes);
            $t2="\n".date("Y-m-d H:i:s");
            $t2.="\nsearchquery:".$searchNumberService->semknoxAttributes['semknoxCorrected'];
            $t2.="\nSemknoxProcessTime:".$searchNumberService->semknoxAttributes['semknoxProcTime']."ms";
            $t2.="\nSemknoxExplanation:".$searchNumberService->semknoxAttributes['semknoxExplanation'];
            $t2.="\nSemknoxResultsAvailable:".$searchNumberService->semknoxAttributes['semknoxResultsAvailable'];
            file_put_contents($this->config['semknoxDebugfile'],"\n\n---------new Search----------".$t2."\n".$t,FILE_APPEND );
        }
				$sort=$searchNumberService->semknoxAttributes['semkonxGetOrderID'];
				$view->sSort = $sort;
				$u=$this->config['semknoxUpdateTemplates'];
				if ($u) {
        	if (Shopware()->Shop()->getTemplate()->getVersion() >= 3) {
        	}
      	}
     }
		/**
		 * Provide the file collection for Less
		 */
		public function addLessFiles(\Enlight_Event_EventArgs $args)
		{
		    $less = new \Shopware\Components\Theme\LessDefinition(
		        array(),
		        array(
		            __DIR__ . '/Resources/views/frontend/_public/src/less/all.less'
		        ),
		        __DIR__
		    );
		    return new \Doctrine\Common\Collections\ArrayCollection(array($less));
		}   		
	private function InsertNewArticleToUpdatelist($article) {
				$sql = "SELECT articleId FROM s_articles_details d where d.ordernumber= ?";
				$params=array($article);
				$id = Shopware()->Db()->fetchOne($sql,$params);  
				if (empty($id)) { return; }
				$sql = "SELECT id FROM semknox_logs l where l.logtitle = ? AND l.status < 101";
				$params=array($article);
				$id = Shopware()->Db()->fetchOne($sql,$params);  
				if (!empty($id)) { return; }
				$sql = "INSERT INTO semknox_logs (logtype, status, logtitle, logdescr) VALUES (111, 0, ?, ?)";
				$params=array($article,'Update für Artikel '.$article);
				$res = Shopware()->Db()->query($sql,$params);  		
	}		
 	public function onPostDispatchArticle(\Enlight_Event_EventArgs $args)  {	
 		$this->getConfig();
 		$u=$this->config['semknoxUpdateSingle'];
 		if ($u==0) return;
    if ( $args->getRequest()->getActionName() === 'save' ) {
			$articleId = $args->getRequest()->getParam('id');
			$sql = "SELECT ordernumber FROM s_articles_details d, s_articles a where d.id = ? AND d.id = a.main_detail_id";
			$params=array($articleId);
			$ordernumber = Shopware()->Db()->fetchOne($sql,$params);  
			if (empty($ordernumber)) { return; }
      $this->InsertNewArticleToUpdatelist($ordernumber);
    } 
    if ( $args->getRequest()->getActionName() === 'saveDetail' ) {
			$articleId = $args->getRequest()->getParam('id');
			$sql = "SELECT ordernumber FROM s_articles_details d where d.id = ? ";
			$params=array($articleId);
			$ordernumber = Shopware()->Db()->fetchOne($sql,$params);  
			if (empty($ordernumber)) { return; }
      $this->InsertNewArticleToUpdatelist($ordernumber);
    } 
	}	
    public function onAfterArticleUpdate(\Enlight_Event_EventArgs $args)
    {
    	$this->getConfig();
 			$u=$this->config['semknoxUpdateSingle'];
	 		if ($u==0) return;
        /**@var $article \Shopware\Models\Article\Article*/
        $article = $args->getEntity();
        if (!($article instanceof \Shopware\Models\Article\Article)) {
            return;
        }
        if (!($article->getId()) > 0) {
            return;
        }
				$sql = "SELECT ordernumber FROM s_articles_details d, s_articles a where d.articleId = ? AND d.id = a.main_detail_id";
				$params=array($article->getId());
				$ordernumber = Shopware()->Db()->fetchOne($sql,$params);  
				if (empty($ordernumber)) { return; }
        $this->InsertNewArticleToUpdatelist($ordernumber);
    }	
    public function onStockChange(\Enlight_Event_EventArgs $args)
    {
    	$this->getConfig();
 			$u=$this->config['semknoxUpdateSingle'];
	 		if ($u==0) return;
        /**@var $article \Shopware\Models\Article\Article*/
        $article = $args->getNumber();
				$this->InsertNewArticleToUpdatelist($article);
    }	
    public function onAfterArticleDetailUpdate(\Enlight_Event_EventArgs $args)
    {
    	$this->getConfig();
 			$u=$this->config['semknoxUpdateSingle'];
	 		if ($u==0) return;
        /**@var $article \Shopware\Models\Article\Article*/
        $article = $args->getEntity();
        if (!($article instanceof \Shopware\Models\Article\Detail)) {
            return;
        }
        if (!($article->getId()) > 0) {
            return;
        }
				$sql = "SELECT ordernumber FROM s_articles_details d where d.id = ?";
				$params=array($article->getId());
				$ordernumber = Shopware()->Db()->fetchOne($sql,$params);  
				if (empty($ordernumber)) { return; }
        $this->InsertNewArticleToUpdatelist($ordernumber);
    }	
		/*
		* prüft, ob User bereits Credentials (api-key und customer-ID) hat. später: Prüfung, ob IDs valide
		* Test, ob wir im Backend sind -> auch hier keine Suche aktivieren
		*/       
    private function checkLoginCreds() {
    	$res=false;
    	$this->getConfig();
    	if ($this->config['semknoxActivate']) {
			$apikey=$this->config['semknoxApiKey'];
			$customerkey=$this->config['semknoxCustomerId'];    	
			if ( (trim($apikey)!='') && (trim($customerkey!='')) ) { $res=true; }
        }
		return $res;
    }
    /**
     * function getsemkShopIDList
     * holt aus DB anhand der Parameter die Shop-IDs, für die ein Update nötig ist
     * return array of shopIDs (int)
     */
    private function getsemkShopIDList() {
        $ret=array();
        $sql = "SELECT cf.name, v.value, v.shop_id  FROM `s_core_config_forms` cf, s_core_config_elements e left JOIN s_core_config_values v ON (v.element_id=e.id) WHERE cf.name='semknoxSearch' AND ((e.name ='semknoxCustomerId') OR (e.name like 'semknoxApiKey') ) AND e.form_id = cf.id";
        $params=array();
        $ha = Shopware()->Db()->fetchAll($sql,$params);
        $h=array();
        foreach($ha as $entr) {
            if (trim(unserialize($entr['value']))!='') { 
                if ($h[$entr['shop_id']]) {$h[$entr['shop_id']]++; } 
                    else {$h[$entr['shop_id']]=1; }
            }
        }
        foreach ($h as $k => $v) {
            if ($v==2) { 
                $params=array();
                $sql="SELECT active from s_core_shops WHERE id = ?";
                $params[]=$k;
                $ha = Shopware()->Db()->fetchOne($sql,$params);
                if ($ha) {  $ret[]=$k; }
            }
        }
        return $ret;
    }
}
