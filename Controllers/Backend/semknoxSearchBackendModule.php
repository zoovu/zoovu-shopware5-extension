<?php
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr\Join;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Article\Repository as ArticleRepo;
use Shopware\Models\Article\SupplierRepository;
use Shopware\Models\Emotion\Repository as EmotionRepo;
use Shopware\Models\Form\Repository as FormRepo;
use semknoxSearch\Bundle\SearchBundle\semknoxBaseApiV3;
use semknoxSearch\bin\semknoxUpdate;
use semknoxSearch\bin\wosoLogger;
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 Logtype: 1		: Update Semknoxdata
 Logtype: 111	: Update Einzelartikel, log_title=odernumber
 	Status: 1		: Update gestartet
 	Status: 101 : Update beendet
 	Status: 102 : Update mit Fehler beendet
 	Status: 111	: Update manuell beendet
 */
class Shopware_Controllers_Backend_semknoxSearchBackendModule extends \Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * @var ArticleRepo
     */
    protected $supplierRepository = null;
    /**
    * Liste mit Log-Infos
    */
    private $loglist = array();
    /**
    * Liste mit Log-Infos für Single-Update, enthält nur Artikelnummern
    */
    private $singlelist = array();
    /**
    *	wenn in Log noch offenes Update steht, dann deaktiviert (0) 
    */
    private $activateUpdateButton = 0;		
    /**
     * Emotion repository. Declared for an fast access to the emotion repository.
     *
     * @var EmotionRepo
     * @access private
     */
    public static $emotionRepository = null;
    /**
     * @var FormRepo
     */
    protected $formRepository = null;
    private $baseapi = null;	
    private $config = null;	
    private $configData = null; 
    private $exConfigData = null; 
    private $locale = "de_DE"; 
    private $update = null; 
    private $memory = null; 
    private $loggerObj = null;
		private function getLogList() {
			$this->loglist=array();$this->activateUpdateButton=0;$this->singlelist=array();
			$sql = "SELECT * FROM semknox_logs WHERE logtype='1' or logtype='111' ORDER BY id DESC";
			$res = Shopware()->Db()->fetchAll($sql);  
			$i=0;$imax=10;
			foreach ($res as $item) {
				if ($item['logtype']==111) { $this->singlelist[]=$item['logtitle']; continue; }
				if ($i==0) {
					if ($item['status']>100) { $this->activateUpdateButton=1;}
				}
				$item['datumi']=strtotime($item['logtime']);
				$item['datumShow']=date("d.m.Y H:i:s",$item['datumi']);
				switch ($item['status']) {
					case 1 : $item['info']='Update gestartet';break;	
					case 2 : $item['info']='Update Fortschritt';break;	
					case 3 : $item['info']='Update Fehler';break;	
					case 101 : $item['info']='Update beendet';break;
					case 102 : $item['info']='Update mit Fehler beendet';break;
					case 111 : $item['info']='Update manuell beendet';break;
					default : $item['info']='warte auf Update';break;
				}
				$this->loglist[]=$item;
				$i++;if ($i>$imax) { break; }
			}
			if ($i==0) { $this->activateUpdateButton=1; }
		}
    public function preDispatch()
    {
    		$this->get('Template')->setTemplateDir([]);
        $this->get('template')->addTemplateDir(__DIR__ . '/../../Resources/views/');
    }
    public function postDispatch()
    {
        $csrfToken = $this->container->get('BackendSession')->offsetGet('X-CSRF-Token');
        $this->View()->assign([ 'csrfToken' => $csrfToken ]);
    }
    /**
     * Registers php shutdown function to catch fatal and parse errors which thrown in refreshPluginList
     */
    private function registerShutdown()
    {
        $this->memory = str_repeat('*', 2024 * 1024);
        register_shutdown_function(function () {
            $this->update=null;
            $this->memory=null;
            $lasterror = error_get_last();
            if (!$lasterror) {
                return;
            }
            $ap = dirname (__FILE__);
            switch ($lasterror['type']) {
                case E_ERROR:
                case E_PARSE:
                case E_CORE_ERROR:
                    ob_clean();
                    ob_flush();
                    http_response_code(200);
                    $message = 'Error<br><br>' . $lasterror['message'] . '<br><br>File:' . str_replace('/', '/ ', $lasterror['file']);
                    $wosoError=0;if ((strpos($lasterror['message'],'memory')) || (strpos($lasterror['message'],'time')) ) { $wosoError=1; }
                    echo json_encode(['success' => false, 'wosoError'=>$wosoError, 'error' => 'Error<br><br>' . $lasterror['message']]);
                    file_put_contents ("$ap/shutdown.log", "\n".date('d.m.Y H:i:s')." ".$message, FILE_APPEND);
                    $this->cancelUpdate($lasterror['message']);
            }
        });
    }
    /**
     * Internal helper function to get access to the form repository.
     *
     * @return SupplierRepository
     */
    private function getSupplierRepository()
    {
        if ($this->supplierRepository === null) {
            $this->supplierRepository = $this->getModelManager()->getRepository('Shopware\Models\Article\Supplier');
        }
        return $this->supplierRepository;
    }
    /**
     * @return FormRepo
     */
    private function getFormRepository()
    {
        if ($this->formRepository === null) {
            $this->formRepository = $this->getModelManager()->getRepository('Shopware\Models\Config\Form');
        }
        return $this->formRepository;
    }
    /**
     * Helper function to get access on the static declared repository
     *
     * @return EmotionRepo
     */
    protected function getEmotionRepository()
    {
        if (self::$emotionRepository === null) {
            self::$emotionRepository = $this->getModelManager()->getRepository('Shopware\Models\Emotion\Emotion');
        }
        return self::$emotionRepository;
    }
    /**
    beendet aktuell laufendes Update
    */
    private function cancelUpdate($logdescr='') {
			$sql = "INSERT INTO semknox_logs (logtype, status, logdescr) VALUES (1,111,?)";
			$params=array($logdescr);
			$res = Shopware()->Db()->query($sql, $params);  
			$this->cleanUpFiles(1);
    }
    /**
    Startet Update über Cronjob
    */
    private function startUpdate() {
    }
    /**
    startet Updateprozess direkt im Backend
    */
    private function startUpdateDirect() {
    	if ($this->config==null) { $this->getConfig(); }
			$this->update = new semknoxUpdate(1);
			$this->update->updateAllBatch($this->config['semknoxUpdateMaxItems']);    	
    }
    public function indexAction()
    {
        $this->registerShutdown(); 
    	if (isset($_GET['semUpdate'])) {
    		if ($_GET['semUpdate']=='02') { $this->cancelUpdate(); }
    		if ($_GET['semUpdate']=='05') { $this->startUpdate(); }
    		if ($_GET['semUpdate']=='72') { $this->startUpdateDirect(); $this->get('Template')->setTemplateDir([]);$this->get('template')->addTemplateDir(__DIR__ . '/../../Resources/views/'); }
    	}
    	$this->getLogList();
    	$this->View()->assign(['semknoxLog'=>$this->loglist, 'semknoxSingleLog'=>$this->singlelist, 'enableUpdateButton'=>$this->activateUpdateButton]);
    }
    private function cleanUpFiles($doall=0) {
    		if (file_exists('./var/log/semkLock.js')) {unlink('./var/log/semkLock.js');}
    		if (file_exists('./var/log/semkDBId.js')) {unlink('./var/log/semkDBId.js');}
    		if (file_exists('./var/log/semkDBArticleIDList.js')) {unlink('./var/log/semkDBArticleIDList.js'); }
    		if (file_exists('./var/log/semkDBNextPos.js')) {unlink('./var/log/semkDBNextPos.js'); }
    		if (file_exists('./var/log/semkDBArticleList.js')) {unlink('./var/log/semkDBArticleList.js'); }
    		if (file_exists('./var/log/semkDBArticleDelList.js')) {unlink('./var/log/semkDBArticleDelList.js'); }
    		if ($doall) {
    		    if (file_exists('./var/log/semkLastCron.txt')) {unlink('./var/log/semkLastCron.txt');}    		    
    		}
    		for ($i = 1; $i < 100000; $i++) {
    			$found=0;
    			$f='./var/log/semkDBArticleList'.str_pad($i, 5 ,'0', STR_PAD_LEFT).'.js';
    			if (file_exists($f)) { $found++; unlink($f); }
    			$f='./var/log/semkDBArticleDelList'.str_pad($i, 5 ,'0', STR_PAD_LEFT).'.js';
    			if (file_exists($f)) { $found++; unlink($f); }
    			if ($found==0) { break; }
    		}
    }    
    public function doUpdateAction() {
        if (is_null($this->loggerObj)) { $this->loggerObj = new wosoLogger(); }
        $this->registerShutdown();
        try {            
            if ($this->config==null) { $this->getConfig(); }
          Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
        	$updInfo=array();
        	$updInfo['status']=0;$updInfo['execTime']=date("d.m.Y H:i:s");
    			$this->update = new semknoxUpdate(1);
    			$this->update->updateAllBatch($this->config['semknoxUpdateMaxItems'], 0);
        	$sql = "SELECT * from semknox_logs WHERE logtype=1 ORDER BY id DESC LIMIT 1";
        	$res = Shopware()->Db()->fetchAll($sql);  			    	
        	$updInfo['erg']=$res[0];
        	file_put_contents('semknoxUpdateFile.js', json_encode($updInfo));
        	echo json_encode($updInfo);
        } catch (Exception $e) {
            $this->loggerObj->error("semknoxUpdate: err: doUpdateAction: ".$e->getMessage());            
       	}
    }
    public function getUpdateInfoAction() {
        $this->registerShutdown();
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
    	$updInfo=array();
    	$updInfo['status']=0;$updInfo['execTime']=date("d.m.Y H:i:s");
    	$sql = "SELECT * from semknox_logs WHERE logtype=1 ORDER BY id DESC LIMIT 1";
    	$res = Shopware()->Db()->fetchAll($sql);  			    	
    	$updInfo['erg']=$res[0];
    	$updInfo['updateRunning']=-1;$updInfo['updateTitle']='';
    	if ( ($res[0]['status']>0) && ($res[0]['status']<100) ) {
    		$updInfo['updateRunning']=intval($res[0]['status']/10); 
    		switch($updInfo['updateRunning']) {
    			case 0	: $updInfo['updateTitle']='Backend-Update, bitte dieses Fenster geöffnet lassen!';break;
    			case 1	: $updInfo['updateTitle']='Cron-Update läuft!';break;
    			case 2	: $updInfo['updateTitle']='Single-Item-Update läuft!';break;
    		}
    	}
    	echo json_encode($updInfo);
    }
    public function exinfo01Action()
    {
        $filter = [];
        $sort = [['property' => 'supplier.name']];
        $limit = 25;
        $offset = 0;
        $query = $this->getSupplierRepository()->getListQuery($filter, $sort, $limit, $offset);
        $total = $this->getModelManager()->getQueryCount($query);
        $suppliers = $query->getArrayResult();
        $this->View()->assign(['suppliers' => $suppliers, 'totalSuppliers' => $total]);
    }
    public function emotionAction()
    {
    }
    public function getEmotionAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer();
        $limit = $this->Request()->getParam('limit', null);
        $offset = $this->Request()->getParam('start', 0);
        $filter = $this->Request()->getParam('filter', null);
        $filterBy = $this->Request()->getParam('filterBy', null);
        $categoryId = $this->Request()->getParam('categoryId', null);
        $query = $this->getEmotionRepository()->getListingQuery($filter, $filterBy, $categoryId);
        $query->setFirstResult($offset)->setMaxResults($limit);
        /**@var $statement PDOStatement */
        $statement = $query->execute();
        $emotions = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $this->View()->assign(['emotions' => $emotions]);
    }
    private function getBaseApi() {
    	if ($this->baseapi!=null) { return ; }
    	if ($this->config==null) { $this->getConfig(); }
    	if (count($this->config)==0) { return; }
    	$sid = '';
    	$this->baseapi = new semknoxBaseApiV3($this->config['semknoxBaseUrl'], $this->config['semknoxCustomerId'], $this->config['semknoxApiKey'], $sid, $this->config['semknoxUseGrouped'], $this->config['semknoxUseHeadOnly']);
    	$this->semknoxBaseApi->addHeaderInfoData($this->getHeaderInfoData());
    }
    private function getConfig() {
    	$this->config = array();
      $repository = $this->getFormRepository();
      $user = Shopware()->Auth()->getIdentity();
      $locale = $user->locale;
      $this->locale=$locale->getLocale();
      $filter = [['property' => 'name', 'value' => 'semknoxSearch']];
      $builder = $repository->createQueryBuilder('form')
          ->select(['form', 'element', 'value', 'elementTranslation', 'formTranslation'])
          ->leftJoin('form.elements', 'element')
          ->leftJoin('form.translations', 'formTranslation', Join::WITH, 'formTranslation.localeId = :localeId')
          ->leftJoin('element.translations', 'elementTranslation', Join::WITH, 'elementTranslation.localeId = :localeId')
          ->leftJoin('element.values', 'value')
          ->setParameter("localeId", $locale->getId());
      $builder->addOrderBy((array) $this->Request()->getParam('sort', []))
          ->addFilter($filter);
      $this->configData = $builder->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
			foreach($this->configData['elements'] as $elm) {
				$this->config[$elm['name']] = "";
				if ( (is_array($elm['values'])) && (count($elm['values'])) ) {
					$this->config[$elm['name']] = $elm['values'][0]['value'];	
				} else {
					$this->config[$elm['name']] = $elm['value'];
				}
			}
			if ($this->config['semknoxUpdateMaxItems'] <= 0) { $this->config['semknoxUpdateMaxItems'] = 5000;}
			try {
			    $this->config['sessionID'] = Shopware()->SessionID();
			} catch (\Exception $e) {
			    $this->config['sessionID'] = 'unknown';
			}
			$this->config['pluginVersion'] = '1.9.31';
			$this->config['shopwareversion'] = Shopware()->Config()->get('version');
			$this->config['semknoxBaseUrl'] = "https://api-shopware.sitesearch360.com/";
    }
		private function addServerConfigData(&$data) {
			$nv=array();$nv['id']=200000001;$nv['name']='sxSearchTail';$nv['value']=$this->exConfigData['sxSearchTail'][0];
			$nv['label']='ergänzende Suchergebnisse';$nv['type']='select';$nv['required']=true;$nv['conftype']='ext';
			$nv['options']=array();$nv['options']['store']=array();
			$opt=array();
			$opt[0]=0;$opt[1]=array('de_DE'=>'keine Ergänzung anzeigen', 'en_GB'=>'no tail');
			$nv['options']['store'][]=$opt;
			$opt[0]=1;$opt[1]=array('de_DE'=>'Ergänzung nur anzeigen, wenn Filter gesetzt', 'en_GB'=>'tail only if no ui filter was set');
			$nv['options']['store'][]=$opt;
			$opt[0]=2;$opt[1]=array('de_DE'=>'Ergänzung immer anzeigen', 'en_GB'=>'full tail, always');
			$nv['options']['store'][]=$opt;
			$data[]=$nv;
		}
		private function getConfigValueByID($id, $data){
			$ret=0;
			foreach($data as $d) {
				if ($d['id']==$id) { $ret=$d;  break;}
			}
			return $ret;
		}
    public function configAction()
    {
    		$this->getBaseApi();
    		if ($this->baseapi==null) { return; }
    		$this->exConfigData = $this->baseapi->getCustomerProperties();
				if  ($this->configData == null) { $this->getConfig(); }
        $data = $this->configData;
        $this->addServerConfigData($data['elements']);
    		if ($_POST['semUpdateConfig']=='05') {
    			$writer = $this->get('config_writer');
    			foreach ($_POST['value'] as $k => $v) {
    					$cv = $this->getConfigValueByID($k, $data['elements']);
    					if (is_array($cv)) {
    						if ($cv['conftype']=='ext') { 
    							$this->baseapi->setCustomerProperties(array($cv['name']=>array($v)));
    						} else {
    							$writer->save($cv['name'],$v);
    						}
    					}
    			}
					$this->configData=array();
					$this->exConfigData = $this->baseapi->getCustomerProperties();
					$this->getConfig();
					$data = $this->configData;
					$this->addServerConfigData($data['elements']);
    		}
        foreach ($data['elements'] as &$values) {
            foreach ($values['translations'] as $array) {
                if ($array['label'] !== null) {
                    $values['label'] = $array['label'];
                }
                if ($array['description'] !== null) {
                    $values['description'] = $array['description'];
                }
            }
            if ( (is_array($values['values'])) && (count($values['values'])>0) ) { $values['value']=$values['values'][0]['value']; }
						if ($values['type']=='boolean') {
							$values['type']='select';
							$values['options']=array();$values['options']['store']=array();
							$h1=array(1, 'ja');$values['options']['store'][]=$h1;
							$h1=array(0, 'nein');$values['options']['store'][]=$h1;
						}
            if (!in_array($values['type'], ['select', 'combo'])) {
                continue;
            }
            foreach($values['options']['store'] as &$st) {
            	$hs="";
            	if (is_array($st[1])) {
            		if ($st[1][$this->locale]) { $hs=$st[1][$this->locale];}
            	}
            	if ($hs!='') { $st[1]=$hs; }
            }
            unset($values);
        }
				$data['locale']=$this->locale;
        $this->View()->assign(['data' => $data]);
    }
    public function createSubWindowAction()
    {
    }
    public function getWhitelistedCSRFActions()
    {
        return ['index', 'getUpdateInfo', 'doUpdate'];
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
}
