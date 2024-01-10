<?php
namespace semknoxSearch\Bundle\SearchBundle;
use Doctrine\Common\Collections\ArrayCollection;
use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\BaseProduct;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Bundle\StoreFrontBundle\Gateway\DBAL\Hydrator\AttributeHydrator;
use semknoxSearch\Bundle\SearchBundle\semknoxBaseApiV3;
use semknoxSearch\bin\wosoLogger; 
/**
 * @category  Shopware
 * @package   Shopware\Bundle\SearchBundleDBAL
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class wosoProductNumberSearch implements SearchBundle\ProductNumberSearchInterface
{
    /**
     * @var FacetHandlerInterface[]
     */
    private $facetHandlers;
    /**
     * @var AttributeHydrator
     */
    private $attributeHydrator;
    /**
     * @var \Enlight_Event_EventManager
     */
    private $eventManager;
    /**
     * zuvor instantiierte product-numbersearch-klasse
     * @var ProductNumberSearchInterface
     */
    private $productNumberSearch;
		/**
		 *  Base-Api-Objekt zur Kommunikation mit Semknox-Server 
		 *	@var semknoxBaseApi
		*/
		private $semknoxBaseApi;
		/**
		 * 
		 * @var ProductNumberSearchInterface
		 */
		private $productnumberSearchInterface;
		/**
		 * Feldname für max. Preis
		 * @var string
		 */
		private $maxPriceFieldName='';
		/**
		 * Feldname für min. Preis
		 * @var string
		 */
		private $minPriceFieldName='';
		/**
		 * Nutze Shopware-Suche. 
		 * @var integer
		 */
		private $useShopwareSearch = 1;
		/**
		 * Liste mit Controllern, bei denen semknoxSuche nicht genutzt werden soll.
		 * @var array[string]
		 */
		private $hideFromController=array('productstream');
		/**
		 * wenn gefüllt, nutze die Suchfunktion nur für folgende Controller!
		 * @var array
		 * special für Listing (widget zum automatischen Nachladen der Seite):
		 */
		private $useOnlyForController=array('search','ajax_search', 'listing');
		/**
		 * nutze vorschlagende Suche von semknox.
		 * @var integer
		 */
		private $doAutoSuggest=0;
		/**
		 * enthält aktuell genutzte Sortier-ID.
		 * @var integer
		 */
		private $getOrderID=-1;																			 
		/**
		 * Konfigurationsdaten des Plugin.
		 *	@var array
		*/
		private $config;
		/**
		 * wurde Semknoxsuche ausgeführt? 0=nein 1=search 2=autosuggest
		 * @var integer
		 */
		public $semknoxSearchSuccess = 0;
		public $semknoxAttributes = array();
		public $debugMode = 0;
		public $debugTimes = array();
		private $shopControllerString = "";
		private $sourceParam=0;
		private $possibleSuggestCats=['categories', 'brands', 'suggests', 'products', 'content1', 'content2', 'content3', 'content4', 'content5' ];	
		private $loggerObj = null;
	/**
	 * Konstruktor der Klasse.
	 * @param ProductNumberSearchInterface $productNumberSearch
	 */
    public function __construct(
    		ProductNumberSearchInterface $productNumberSearch
    ) {
        $this->loggerObj = new wosoLogger();
        $this->productNumberSearch=$productNumberSearch;
        $this->attributeHydrator = Shopware()->Container()->get('shopware_storefront.attribute_hydrator_dbal');
        $this->eventManager = Shopware()->Container()->get('events');
        $this->minPriceFieldName = 'priceMin';
        $this->maxPriceFieldName = 'priceMax';
        try {
            if ( (is_object(Shopware())) && (is_object(Shopware()->Front())) && (is_object(Shopware()->Front()->Request())) ) {
                $controllerName = strtolower(Shopware()->Front()->Request()->getControllerName());
                $moduleName = strtolower(Shopware()->Front()->Request()->getModuleName());
            } else {
                $this->useShopwareSearch=1;
                $this->semknoxSearchSuccess=0;
                return;                
            }
        } catch (\Exception $e) {
            $this->useShopwareSearch=1;
            $this->semknoxSearchSuccess=0;
            return;
        }
        if ( (in_array($controllerName, $this->hideFromController))  ) {
        	$this->useShopwareSearch=1;
        	$this->semknoxSearchSuccess=0;
					return;        	        	        	
        }
        if (count($this->useOnlyForController)) {
            if  (!in_array(strtolower($controllerName), $this->useOnlyForController))  {
              $this->useShopwareSearch=1;
              $this->semknoxSearchSuccess=0;
              return;
            }
        }
        $this->shopControllerString="$controllerName##$moduleName##".Shopware()->Front()->Request()->getRequestUri()."##".Shopware()->Front()->Response()->getHttpResponseCode();
        $this->doAutoSuggest=0;
        if ($controllerName=='ajax_search') { $this->doAutoSuggest=1; }
        if  ( ($moduleName!='frontend') || ($controllerName=='listing') ) {
        	if ( ($moduleName=='widgets') && ($controllerName=='listing') ) { $this->useShopwareSearch=0;}  else {
        		$this->useShopwareSearch=1;
        		$this->semknoxSearchSuccess=0;
						return;        	
					}
        }
		$this->getConfig();
        try {
            $sessId = Shopware()->Session()->get('sessionId');
        } catch (\Exception $e) {
            $this->loggerObj->error("Semknox: Fehler bei SessionID in ".Shopware()->Front()->Request()->getModuleName(). "/".Shopware()->Front()->Request()->getControllerName());            
            $this->useShopwareSearch=1;
            $this->semknoxSearchSuccess=0;            
            return;
        }				
    	$this->semknoxBaseApi = new semknoxBaseApiV3($this->config['semknoxBaseUrl'], $this->config['semknoxCustomerId'], $this->config['semknoxApiKey'], $sessId, $this->config['semknoxUseGrouped'], $this->config['semknoxUseHeadOnly']);
    	$this->semknoxBaseApi->addHeaderInfoData($this->getHeaderInfoData());
    	$this->semknoxBaseApi->shopControllerString=$this->shopControllerString;
    	$this->semknoxBaseApi->debugMode=$this->debugMode;
        $this->facetHandlers = $this->registerFacetHandlers();
		$this->useShopwareSearch=0;
		if ( ($controllerName=='listing') && ($moduleName=='widgets') ) {
		  $this->sourceParam=1;
		}
		$response = Shopware()->Front()->Response();
		if ( (is_object($response)) && ($response->getHttpResponseCode() == 404) ) {
		    $this->sourceParam=2;
		}
    }
    /**
     * set new DebugTime for field 
     * @param String $field - field to set time of
     * @param String $info - title of time
     */
    private function pushDebugTime(String $field, String $info) {
        if ($this->debugMode > 0) {
            if (!isset($this->debugTimes[$field])) { $this->debugTimes[$field] = array(); }
            $a = array(microtime(true), $info);
            $this->debugTimes[$field][]=$a;
        }
    }
    /**
     * Wandelt unterschiedliche Konfigurations-Werte zu Zahlenwerten um.
     * Gibt angegebenen Wert aus der Konfiguration als Zahl zurück. Boolean->0|1 falls _woso_ -> danach stehende Zahl
     * @param mixed $v
     * @param mixed $def
     * @return mixed
     */
    private function getConfigSelectIntValue( $v, $def=0) {
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
    private function getSuggestCatsArray($cattext) {
        $ret=[];
        if (trim($cattext)=='') { return $ret; }
        $h = explode('|',$cattext);
        foreach($h as $cat) {
            $cat = strtolower($cat);
            if (in_array($cat, $this->possibleSuggestCats)) { $ret[]=$cat; }
        }
        return $ret;
    }
    private function getSuggestCatTitles($cattext) {
        $ret=[];
        if (trim($cattext)=='') { return $ret; }
        $h = explode('|',$cattext);
        foreach($h as $catt) {
            $h2 = explode("=",$catt);
            if (count($h2)==2) {
                $cat = strtolower($h2[0]);$cattitle=$h2[1];
                if (in_array($cat, $this->possibleSuggestCats)) { $ret[$cattitle]=$cat; }
            }
        }        
        return $ret;
    }
    /**
     * speichere Plugin-Konfiguration in internem Feld ab.
     * @return void
     */
    private function getConfig() {
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
    		$this->config['semknoxUpdateMaxItems']=intval(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxUpdateMaxItems'));if ($this->config['semknoxUpdateMaxItems']<=0) { $this->config['semknoxUpdateMaxItems'] = 5000; }
    		if (trim($this->config['semknoxRankingAttribute'])=='') { $this->config['semknoxRankingAttribute']='UNIX_TIMESTAMP(d.releaseDate)'; }    		
    		$this->config['semknoxUseAutosug'] = 1;
    		$this->config['semknoxUseFallback'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxUseFallback'),1); 
    		$this->config['semknoxAppendNoStock'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxAppendNoStock'),0);
    		$this->config['semknoxDeleteNoStock'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxDeleteNoStock'),0);    		
    		$this->config['semknoxPreferPrimaryVariant'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxPreferPrimaryVariant'),0);
    		$this->config['hideNoStock'] = Shopware()->Config()->get('hidenoinstock');
    		$this->config['shopwareversion'] = Shopware()->Config()->get('version');
    		$this->config['semknoxRegEx'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxRegEx'));
    		$this->config['semknoxRegExRepl'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxRegExRepl'));
    		$this->config['semknoxDebugfile'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxDebugfile'));
    		try {
    		    $this->config['sessionID'] = Shopware()->SessionID();
    		} catch (\Exception $e) {
    		    $this->config['sessionID'] = 'unknown';
    		}
    		$this->config['pluginVersion'] = '1.9.31';
    		$this->config['semknoxRedirectOneProduct'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxRedirectOneProduct'),0);
    		$this->config['semknoxSuggestCatSort'] = $this->getSuggestCatsArray(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxSuggestCatSort'));
    		$this->config['semknoxSuggestCatRel'] = $this->getSuggestCatTitles(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxSuggestCatRel'));
    		$this->config['semknoxSuggestImgBaseUrl'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxSuggestImgBaseUrl'));
    		if ( (trim($this->config['semknoxSuggestImgBaseUrl']!='')) && (substr($this->config['semknoxSuggestImgBaseUrl'], -1) != '/') ) {
    		    $this->config['semknoxSuggestImgBaseUrl'] .= '/';
    		}
    		$this->config['semknoxSuggestUseShopwareProducts'] = $this->getConfigSelectIntValue(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxSuggestUseShopwareProducts'),0);
    		$this->config['semknoxOrderScoreText'] = trim(Shopware()->Config()->getByNamespace('semknoxSearch','semknoxOrderScoreText'));
    		if ($this->config['semknoxOrderScoreText'] == '') { $this->config['semknoxOrderScoreText'] = 'Relevanz'; }
    		if (trim($this->config['semknoxDebugfile'])!='') { $this->debugMode=10; }
    }
    /**
     * gibt transformierten Querystring nach regulären Ausdrücken zurück
     * @param string $term
     * @return string
     */
    private function getQueryString($term) {
        if (trim($term)=='') { return $term; }
        if ( ($this->config['semknoxRegEx']!='') && ($this->config['semknoxRegExRepl']!='') ) {
            try {
                $term = preg_replace($this->config['semknoxRegEx'], $this->config['semknoxRegExRepl'], $term);
            } catch (\Exception $e) {
                $err=1;
            }
        }
        return $term;
    }
    /**
     * Füge Query-Paramter zu Query-String zusammen
     * @param array $params
     * @return string
     */
    private function queryAssemble(array $params) {
    	$res="/search";
    	$pa="";
    	foreach($params as $k => $p) {
    		if ($pa!='') { $pa.='&'; }
    		$pa.=$k."=".urlencode($p);
    	}
    	if ($pa!='') { $res=$res.'?'.$pa; }
    	return $res;
    }
    /**
     * gibt das Filter-Array als String der Form 1234_9999|1235_8888 zurück.
     * @param array $filter
     * @return string
     */
    private function getFilterString(array $filter) {
    	$res="";
    	foreach ($filter as $k => $fi) {
    		foreach ($fi as $f) {
    			if ($res!='') { $res.='|'; }
    			$res.=$k."_".$f;
    		}
    	}
    	return $res;
    }
    private function getRewrUrl($params) {
        $ret='';
        $params=array("sViewport=cat&sCategory=".$params['sCategory']);
        $query = "SELECT path from s_core_rewrite_urls WHERE org_path = ? AND main='1'";
        $dbResult = Shopware()->Db()->fetchAll($query,$params);
        if (count($dbResult)) {
            $ret=$dbResult[0]['path'];
        }
        return $ret;
    }
    private function doCatDirectSearch(SearchBundle\Criteria $criteria) {
        $ret=false;
        if  ( ($this->config['semknoxRewriteCat']) ) {
            $condition=$criteria->getCondition('search');$term=$this->getQueryString($condition->getTerm());
            $query="SELECT id from s_categories WHERE description like ? and active > 0";
            $params=array($term);
            $dbResult = Shopware()->Db()->fetchAll($query,$params);
            $catid=0;
            if (count($dbResult)) {
                $catid=$dbResult[0]['id'];
            }
            if ($catid>0) {
                $params=array( 'controller' => 'listing',   'action' =>  'index', 'sCategory' => $catid);
                $url=$this->getRewrUrl($params);
                if ($url=='') {
                    /*
                     $response = new \Enlight_Controller_Response_ResponseHttp();
                     $response->setRedirect($params);
                     $response->sendResponse();
                     exit();
                     */
                    $url = Shopware()->Container()->get('front')->Router()->assemble($params);
                } else {
                    if (!preg_match('#^(https?|ftp)://#', $url)) {
                        if (strpos($url, '/') !== 0) {
                            $url = Shopware()->Container()->get('front')->Request()->getBaseUrl() . '/' . $url;
                        }
                        $uri = Shopware()->Container()->get('front')->Request()->getScheme() . '://' . Shopware()->Container()->get('front')->Request()->getHttpHost();
                        $url = $uri . $url;
                    }
                }
                Shopware()->Front()->Response()->setRedirect($url, $this->config['semknoxRedirCode']);
                $ret=true;
            }
        }
        return $ret;
    }
    /**
     * Creates a product search result for the passed criteria object.
     * The criteria object contains different core conditions and plugin conditions.
     * This conditions has to be handled over the different condition handlers.
     *
     * The search gateway has to implement an event which plugin can be listened to,
     * to add their own handler classes.
     *
     * @param SearchBundle\Criteria $criteria
     * @param ShopContextInterface $context
     * @return SearchBundle\ProductNumberSearchResult
     */
    public function search(SearchBundle\Criteria $criteria, ShopContextInterface $context)
    {
    	/*
				if (!$criteria->hasCondition('search')) {    		
					return 
				}
    	*/	
        $this->pushDebugTime('search', 'start PluginSearch');
        if ( (!$criteria->hasCondition('search')) || ($this->useShopwareSearch) ) {
            $this->semknoxSearchSuccess=0;            
            return $this->productNumberSearch->search($criteria, $context);
        }    	
    		if (($this->doAutoSuggest) && ($this->config['semknoxUseAutosug'])) {
    			$erg=$this->getAutoSuggest($criteria, $condition);
    			$this->semknoxSearchSuccess=2;
    			if ( ($erg===false) && ($this->config['semknoxUseFallback']) && ($this->semknoxAttributes['flyoutAddItems'] == 0) ) {
    				$erg = $this->productNumberSearch->search($criteria, $context);
    				$this->semknoxSearchSuccess=0;    				
    			}
    			$this->pushDebugTime('search', 'autosug - end');
    			if ($erg == false) {
    			    return new SearchBundle\ProductNumberSearchResult(
    			        [],
    			        0,
    			        []
    			        );
    			}
    			return $erg;
    		} else {
    		  if ($this->doCatDirectSearch($criteria)) {
    		      $this->pushDebugTime('search', 'doCatDirectSearch - end');
    		      return new SearchBundle\ProductNumberSearchResult([], 0, []);
    		  }
    		}
    		$orderID=-1;$orderDirection='ASC';$filter=array();
    		$condition=$criteria->getCondition('search');
    		$term=$this->getQueryString($condition->getTerm());
    		$this->getOrderInfov3($orderID);
    		$filter=$this->getPropertyConditionFromRequest();    
    		$this->getPriceFilterFromRequest($filter);		
    		$this->getMinMaxFilterFromRequest($filter);
            $this->semknoxBaseApi->sourceParam=$this->sourceParam;
            $this->pushDebugTime('search', 'start Semknoxquery');
            $this->semknoxBaseApi->QuerySearchResultsOrderby($term,$orderID,$orderDirection,$filter,$criteria->getOffset(),$criteria->getLimit());
            $this->pushDebugTime('search', 'stop Semknoxquery');
            $this->semknoxBaseApi->sourceParam=0;
        if ($this->semknoxBaseApi->redirectResult!='') {
					Shopware()->Front()->Response()->setRedirect($this->semknoxBaseApi->redirectResult, 301);        	
					return new SearchBundle\ProductNumberSearchResult([], 0, []);					
        }
    		$total = $this->semknoxBaseApi->getSearchResultsCount();
    		$this->semknoxSearchSuccess=1;    	
    		$sdata=array(
    		    'semknoxExplanation'=>$this->semknoxBaseApi->explanation,
    		    'semknoxProcTime'=>$this->semknoxBaseApi->processingTime,
    		    'semknoxConfidence'=>$this->semknoxBaseApi->confidence,
    		    'semknoxCorrected'=>$this->semknoxBaseApi->corrected,
    		    'semknoxOrder'=>[],
    		    'semkonxGetOrderID'=>$this->getOrderID,
    		    'semknoxSuggests' => $this->semknoxBaseApi->suggests,
    		    'semknoxResultsAvailable' => $this->semknoxBaseApi->resultsAvailable,
    		    'contentSearchResults' => $this->semknoxBaseApi->contentSearchResults
    		);
    		if ($total <= 0) {
    		    Shopware()->Session()->semknoxSearchResults=$sdata;
    		    $h = new \Shopware\Bundle\StoreFrontBundle\Struct\Attribute($sdata);
    		    $this->semknoxAttributes=$h->toArray();
    		    $this->pushDebugTime('search', 'start fallbacksearch');    		    
	   			if ( ($this->config['semknoxUseFallback']) ) {
	   			    $this->semknoxSearchSuccess=0;	   			    
  	 				return $this->productNumberSearch->search($criteria, $context);
  				} else {
  				    return new SearchBundle\ProductNumberSearchResult([], 0, []);  				    
  				}
    		}
    		$this->pushDebugTime('search', 'start getProducts');
    		$products = $this->getProducts();
    		$this->pushDebugTime('search', 'stop getProducts');
    		if (count($products)<=0) {
	   			if ( ($this->config['semknoxUseFallback']) ) {
	   			    $this->semknoxSearchSuccess=0;	   			    
  	 				return $this->productNumberSearch->search($criteria, $context);
  				} else {
  				    return new SearchBundle\ProductNumberSearchResult([], 0, []);  				    
  				}
  			}
  			$this->pushDebugTime('search', 'start getFacets');
  			$facets = $this->createFacets($criteria, $context);
  			$this->pushDebugTime('search', 'stop getFacets');
  			$this->pushDebugTime('search', 'start getResult');
  			$result = new SearchBundle\ProductNumberSearchResult(
                $products,
                intval($total),
                $facets
            );
  			$this->pushDebugTime('search', 'stop getResult');
            $order=array();
   			$baselinkdata=array('sSearch'=>$term);			
   			if (count($filter)) {$baselinkdata['f']=$this->getFilterString($filter); }
   			/*   			if ( (is_array($this->semknoxBaseApi->orderSet)) && (count($this->semknoxBaseApi->orderSet)) ) {
   				$this->setOrderInfo($orderID,$orderDirection,$this->semknoxBaseApi->orderSet);
   			}*/
   			$sortfound=0;
   			$so=[];
   			$so['name']=$this->config['semknoxOrderScoreText'];
   			$so['key']='score';
   			$so['type']='ATTRIBUTE';
   			$so['sort']='ASC';
   			$so['id']="999521972";
   			$so['viewName']=$this->config['semknoxOrderScoreText'];
   			$so['active']=0;
   			$order[]=$so;
   			foreach($this->semknoxBaseApi->order as $so) {
   			    $orderKey = $so['key'];
   			    $so['active']=0;
   			    if (	(is_array($this->semknoxBaseApi->orderSet)) &&
   			        (isset($this->semknoxBaseApi->orderSet['name'])) &&
   			        ($this->semknoxBaseApi->orderSet['name'] == $so['name'])
   			        ) {
   			            $so['active']=1;
   			            $sortfound=1;
   			        }
   			        $order[]=$so;
   			}
   			if ($sortfound==0) { $order[0]['active']=1; }
            $sdata=array(
                'semknoxExplanation'=>$this->semknoxBaseApi->explanation,
                'semknoxProcTime'=>$this->semknoxBaseApi->processingTime,
                'semknoxConfidence'=>$this->semknoxBaseApi->confidence,
                'semknoxCorrected'=>$this->semknoxBaseApi->corrected,
                'semknoxOrder'=>$order,
                'semkonxGetOrderID'=>$this->getOrderID,
                'semknoxSuggests' => $this->semknoxBaseApi->suggests,
                'semknoxResultsAvailable' => $this->semknoxBaseApi->resultsAvailable,
                'contentSearchResults' => $this->semknoxBaseApi->contentSearchResults
            );
            Shopware()->Session()->semknoxSearchResults=$sdata;
            $h = new \Shopware\Bundle\StoreFrontBundle\Struct\Attribute($sdata);
            $result->addAttribute('semknoxData', $h);            
            $this->semknoxAttributes=$h->toArray();
            $this->pushDebugTime('search', 'stop PluginSearch');
            if ($this->debugMode > 0) {
                foreach ($this->semknoxBaseApi->debugTimes as $k => $v) {
                    $this->debugTimes[$k]=$v; 
                }
            }
            /*
    				$result->addAttribute('semknoxExplanation',$this->semknoxBaseApi->explanation);
    				$result->addAttribute('semknoxProcTime',$this->semknoxBaseApi->processingTime);
    				$result->addAttribute('semknoxConfidence',$this->semknoxBaseApi->confidence);
    				$result->addAttribute('semknoxCorrected',$this->semknoxBaseApi->corrected);
    				*/
            return $result;
    }
    /**
     * füllt die Parameter OrderID und OrderDir entsprechend dem Request
     * @param int $orderID
     * @param string $orderDir
     * @return void
     */
    private function getOrderInfo(int &$orderID, string &$orderDir) {
    	$orderID=-1;$orderDir='ASC';
			$request = Shopware()->Container()->get('front')->Request();    	
			$od = intval($request->getParam('o', []));
			if ($od>1000000000) {
				if ($od > 2000000000) {
					$orderID=$od-2100000000;
					$orderDir="DESC";
					$this->getOrderID=$od;
				} else {
					$orderID=$od-1100000000;
					$orderDir="ASC";
					$this->getOrderID=$od;
				}
			}
			if ($orderID<0) {
				$orderID=-1;$orderDir='ASC';
			}
    }
    /**
     * füllt die Parameter OrderID und OrderDir entsprechend dem Request
     * @param int $orderID
     * @param string $orderDir
     * @return void
     */
    private function getOrderInfov3(&$orderID) {
        $orderID=-1;$orderDir='ASC';
        $request = Shopware()->Container()->get('front')->Request();
        $od = $request->getParam('o', []);
        $orderID = $od;
        if ($od == 'score') { $orderID = -1; }
    }
    /**
     * füllt die Parameter OrderID und OrderDir entsprechend dem orderSet aus BaseApi
     * @param int $orderID
     * @param string $orderDir
     * @param array $orderSet
     * @return void
     */
    private function setOrderInfo(int &$orderID, string &$orderDir, array $orderSet) {
    	$orderID=-1;$orderDir='ASC';
    	if (! is_array($orderSet)) { return; }
    	foreach($orderSet as $k => $v) {
    		$orderID=intval($k);
    		$orderDir=$v;
    		break;
    	}
    }
    /**
     * Funktion gibt zur übergebenen Ordernumber die Array mit Shopware-IDs zurück
     * @param string $ordernumber
     * @return array
     */
		private function getArticleByOrdernumber(string $ordernumber) {
			$ret = array();
			$query="SELECT id as variantid, articleID as id from s_articles_details WHERE ordernumber = ?";
			$params=array($ordernumber);
	    $dbResult = Shopware()->Db()->fetchAll($query,$params);
	    if ( (is_array($dbResult)) && (count($dbResult)) ) {
	    	$ret=$dbResult[0];
	    }
			return $ret;
		}    
    /**
     * gibt zur Suchergebnissen aus Semknox-API die Produktliste zurück.
     * @return BaseProduct[]
     */
    private function getProducts()
    {
        $products = [];
        foreach ($this->semknoxBaseApi->getSearchResults(0, $this->config['semknoxPreferPrimaryVariant']) as $row) {
        		$a = $this->getArticleByOrdernumber($row['articleNumber']);
        		if (count($a)==0) continue;
        		$row['__product_id']=$a['id'];
        		$row['__variant_id']=$a['variantid'];
        		$row['__variant_ordernumber']=$row['articleNumber'];
            $product = new BaseProduct(
                (int) $row['__product_id'],
                (int) $row['__variant_id'],
                $row['__variant_ordernumber']
            );
						if ($this->attributeHydrator) {
            	$product->addAttribute('search', $this->attributeHydrator->hydrate($row));
          	}
            $products[$product->getNumber()] = $product;
        }
        return $products;
    }
    /**
     * Gibt zur vorschlagenden-Suche-Ergebnis von Semknox die Produktliste zurück.
     * @return Baseproduct[]
     */
    private function getAutosuggestProducts()
    {
        $products = [];
		foreach($this->semknoxBaseApi->suggests['products']['items'] as $rowm) {
		      $row=$rowm[0];
        		$a = $this->getArticleByOrdernumber($row['identifier']);
        		if (count($a)==0) { continue; }
        		$row['__product_id']=$a['id'];
        		$row['__variant_id']=$a['variantid'];
        		$row['__variant_ordernumber']=$row['identifier'];
            $product = new BaseProduct(
                (int) $row['__product_id'],
                (int) $row['__variant_id'],
                $row['__variant_ordernumber']
            );
            $products[$product->getNumber()] = $product;
        }
        return $products;
    }
    /**
     * @param SearchBundle\Criteria $criteria
     * @param ShopContextInterface $context
     * @return SearchBundle\FacetResultInterface[]
     * @throws \Exception
     */
    private function createFacets(SearchBundle\Criteria $criteria, ShopContextInterface $context)
    {
        $facets = [];
        foreach ($criteria->getFacets() as $facet) {
            $handler = $this->getFacetHandler($facet);
            $result = $handler->generateFacet($facet, $criteria, $context);
            if (!$result) {
                continue;
            }
            if (!is_array($result)) {
                $result = [$result];
            }
            $facets = array_merge($facets, $result);
        }
        return $facets;
    }
    /**
     * erstelle FacetHandler-Liste.
     * @return FacetHandlerInterface[]
    */ 
    private function registerFacetHandlers() {
    		$ret=array();
    		$ret[]=new \semknoxSearch\Bundle\SearchBundle\FacetHandler\PropertyFacetHandler($this->semknoxBaseApi);
    		$ret[]=new \semknoxSearch\Bundle\SearchBundle\FacetHandler\PriceFacetHandler($this->semknoxBaseApi);
    		$ret[]=new \semknoxSearch\Bundle\SearchBundle\FacetHandler\BaseFacetHandler;
    		return $ret;
    }
    /**
     * hole FacetHandler zu gefragtem Facet.
     * @param SearchBundle\FacetInterface $facet
     * @throws \Exception
     * @return FacetHandlerInterface
     */
    private function getFacetHandler(SearchBundle\FacetInterface $facet)
    {
        foreach ($this->facetHandlers as $handler) {
            if ($handler->supportsFacet($facet)) {
                return $handler;
            }
        }
      	return new \semknoxSearch\Bundle\SearchBundle\FacetHandler\BaseFacetHandler;
    }
    /**
     * @param ArrayCollection $objects
     * @param string $class
     */
    private function assertCollectionIsInstanceOf(ArrayCollection $objects, $class)
    {
        foreach ($objects as $object) {
            if (!$object instanceof $class) {
                throw new \RuntimeException(
                    sprintf(
                        'Object of class "%s" must be instance of "%s".',
                        get_class($object),
                        $class
                    )
                );
            }
        }
    }
    /**
     * hole Filterinformationen aus Request.
     * @return array[]
     */
    public function getPropertyConditionFromRequest() {
    	$request = Shopware()->Container()->get('front')->Request();
    	$filters = $request->getParam('sFilterProperties', []);
    	$filters = explode('|', $filters);$ret=array();
			foreach($filters as $prop) {
				$x1=explode('_',$prop);
				if (count($x1)>=2) {
					if (!isset($ret[$x1[0]])) {$ret[$x1[0]]=array();}
					$key=$x1[0];
					unset($x1[0]);
					$val = implode('_',$x1);
					$ret[$key][]=$val;
				}
			}
    	return $ret;
    } 
    /**
     * hole MinMax-Filter aus Request.
     * @param array $filter
     * @return void
     */
    public function getMinMaxFilterFromRequest(array &$filter) {
    	$request = Shopware()->Container()->get('front')->Request();
    	$params = $request->getParams();
    	$minmaxlist = array();
    	foreach($params as $paramId => $param) {
    		if (strtolower(substr($paramId,0,4))=='semk') {
    			$parts=explode('_',$paramId);
    			if (count($parts)>1) {
    				$fid=$parts[1];
    				if (!is_array($minmaxlist[$fid])) { $minmaxlist[$fid]=array(); }
    				if (strtolower($parts[0])=='semkmin') {
    					$minmaxlist[$fid]['min']=$param;
    				}
    				if (strtolower($parts[0])=='semkmax') {
    					$minmaxlist[$fid]['max']=$param;    					
    				}
    				if (count($parts>2)) {
    					$minmaxlist[$fid]['unitName']=$parts[2];
    				}
    			}
    		}
    	}
    	foreach ($minmaxlist as $k => $v) {
    		$a=array();
    		if (isset($v['min'])) { $a['min']=floatval($v['min']); }
    		if (isset($v['max'])) { $a['max']=floatval($v['max']); }
    		if (isset($v['unitName'])) { $a['unitName']=$v['unitName']; }
    		$filter[$k]=$a;
    	}
    }    
    /**
     * hole Preisfilter aus Request.
     * @param array $filter
     * @return void
     */
    public function getPriceFilterFromRequest(array &$filter) {
    	$ret=array();
    	$request = Shopware()->Container()->get('front')->Request();
    	$params = $request->getParams();
    	$c1=strlen($this->minPriceFieldName);
    	$c2=strlen($this->maxPriceFieldName);
    	$minf='';$maxf='';
    	foreach($params as $k => $v) {
    		$s1 = substr($k,0,$c1);
    		$s2 = substr($k,0,$c2);
    		if ($s1==$this->minPriceFieldName) { $minf=$k; $pid=intval(substr($k,$c1+1,1000)); }
    		if ($s2==$this->maxPriceFieldName) { $maxf=$k; $pid=intval(substr($k,$c1+1,1000));}
    	}
    	$activeMin=0;
    	$activeMax=0;
   		if ($maxf) { $activeMax=floatval($params[$maxf]); }
   		if ($minf) { $activeMin=floatval($params[$minf]); if ($activeMax==0) { $activeMax=222222222222;}}
   		if (($activeMin>0) || ($activeMax>0)) {
   				$filter[$pid]=array('min'=>$activeMin,'max'=>$activeMax);
   		}
    }
    /**
     * schneidet Domain aus URI heraus
     * @param string $path
     * @return string
     */
	private function getUriPathOnly(string $path) {
	  $res=$path;
	  $h=parse_url($path);
	  $res = $h['path'];
      if (trim($h['query'])!='') {$res.='?'.$h['query'];}
      if (trim($h['fragment'])!='') {$res.='#'.$h['fragment'];}			
      $res=ltrim($res,'/');
      return $res;
	}    
	private function getShowTitle($title, $search) {
	    $ret = $title;
	    $ret = preg_replace('#'. preg_quote($search) .'#i', '<span class="sx-match">\\0</span>', $title);
	    return $ret;
	}
	private function genFullImageURL($url) {
	    $ret = trim($url);
	    if ($ret=='') { return $ret;}
	    $newurl = parse_url($url);
	    if (! isset($newurl['host']) && ($this->config['semknoxSuggestImgBaseUrl']) && (isset($newurl['path'])) ) {
	        $newurl = parse_url($this->config['semknoxSuggestImgBaseUrl'].$newurl['path']);
	    }
	    if ( (isset($newurl['host'])) && (isset($newurl['path'])) ) {
	        if (!isset($newurl['scheme'])) { $ret='//'; } else {$ret = $newurl['scheme']."://"; }
	        $ret.=$newurl['host'].$newurl['path'];
	        if (isset($newurl['query'])) { 
	            $ret.='?'.$newurl['query'];
	        }
	    }
	    return $ret;
	}
	/**
	 * bereitet die Suchvorschläge für die Anzeige im Flyout vor
	 * gibt Anzahl der zu zeigenden Zusatzeinträge aus, sonst 0
	 * @param array $suggests
	 * @return void|number
	 */
	private function addSuggestData($suggests, &$countItems, $term) {
	    $countItems = 0;
	    if (!is_array($suggests)) { return $suggests; }
	    $newSuggests = array();
	    if (count($this->config['semknoxSuggestCatSort']) <= 0) {
	        return [];
	    }
	    $suggests = Shopware()->Container()->get('events')->filter(
	        'SemknoxSearch_Suggests_Filter',
	        $suggests,
	        [
	            'suggests' => $suggests,
	            'term' => $term
	        ]
	        );
	    foreach ($suggests as $k => $sug) {
	        $sug = Shopware()->Container()->get('events')->filter(
	            'SemknoxSearch_SuggestData_Filter',
	            $sug,
	            [
	                'suggest' => $sug,
	                'suggestId' => $k
	            ]
	            );
	        $newSug=['title'=>$sug['title'], 'id'=>$k, 'showFlyout'=>0, 'showTitle'=>1, 'items'=>[]];	        
	        if (in_array($k, $this->config['semknoxSuggestCatSort'])) { $newSug['showFlyout'] = 1;}
	        foreach ($sug['items'] as $item) {
	            $baseItem = $item;if ( ($k=='products') && (is_array($item)) && (isset($item[0])) ) { $baseItem = $item[0]; }
	            $item['showTitle'] = $this->getShowTitle($baseItem['name'], $term);
	            if (trim($item['link'])=='') {
	                /*
	                 if ($k=='products') {
	                 $item['link'] = Shopware()->Front()->Router()->assemble(['controller'=>'detail', 'sArticle'=>$baseItem['identifier']]);
	                 } else {
	                 */
	                if ( ($k=='products') && ($baseItem['link'])) {
	                    $item['link'] = $baseItem['link'] ;
	                } else {
	                    $item['link'] = Shopware()->Front()->Router()->assemble(['controller'=>'search', 'action'=>'index', 'sSearch'=>$baseItem['name']]);
	                }
	            }
	            $item['image'] = $this->genFullImageURL($baseItem['image']);
	            $newSug['items'][]=$item;
	            if ($newSug['showFlyout']) { $countItems++; }
	        }
	        if (count($newSug['items']) <= 0) { $newSug['showFlyout'] = 0; }
	        $newSuggests[$k]=$newSug;
	    }
	    $hs = [];$f=0;
	    foreach ($this->config['semknoxSuggestCatSort'] as $cat) {	        	        
	        if (isset($newSuggests[$cat])) { 
	            if (($f==0) && ($cat=='suggests')) { 
	                $newSuggests[$cat]['showTitle']=0;
	            }
	            $hs[]=$newSuggests[$cat]; 
	            $f++; 
	        }
	    }
	    foreach ($newSuggests as $k => $items) {
	        if (!in_array($k, $this->config['semknoxSuggestCatSort'])) { 
	            $hs[]=$items;
	        }
	    }
	    return $hs;
	}
	/**
	 * hole aus vorschlagender Suche die Daten zu Criteria.
	 * @param SearchBundle\Criteria $criteria
	 * @return boolean|\Shopware\Bundle\SearchBundle\ProductNumberSearchResult
	 */
	private function getAutoSuggest(SearchBundle\Criteria $criteria) {
  	 $condition=$criteria->getCondition('search');
  	 $term=$this->getQueryString($condition->getTerm());
	 $this->semknoxBaseApi->QuerySuggests($term, $criteria->getLimit(), 'de', $this->config['semknoxSuggestCatRel']);
     $products = $this->getAutosuggestProducts();
     if (! in_array('products', $this->config['semknoxSuggestCatSort'])) { $products = []; }
     if ($this->config['semknoxSuggestUseShopwareProducts'] == 0) { 
           $products = []; 
     }
   	 $total = count($products);
   	 $suggestResults = $this->addSuggestData($this->semknoxBaseApi->suggests, $flyoutAddItems, $term);
   	 if ($this->config['semknoxSuggestUseShopwareProducts'] == 1) { 
   	     $hn=[];
   	     foreach ($suggestResults as $item) {
   	         if ($item['id'] != 'products') {$hn[]=$item;}
   	     }
   	     $suggestResults=$hn;
   	 }
   	 $showSemknoxSuggests = 1;
   	 if (count($this->config['semknoxSuggestCatSort']) <= 0) {
   	     $showSemknoxSuggests = 0;
   	 }
   	 $this->semknoxAttributes=array(
   	     'semknoxExplanation'=>$this->semknoxBaseApi->explanation,
   	     'semknoxProcTime'=>$this->semknoxBaseApi->processingTime,
   	     'semknoxConfidence'=>$this->semknoxBaseApi->confidence,
   	     'semknoxCorrected'=>$this->semknoxBaseApi->corrected,
   	     'semknoxOrder'=>$order,
   	     'semkonxGetOrderID'=>$this->getOrderID,
   	     'semknoxSearchResultCount' => -1,
   	     'semknoxSuggests' => $suggestResults,
   	     'flyoutAddItems' => $flyoutAddItems,
   	     'showSemknoxSuggests' => $showSemknoxSuggests,
   	     'contentSearchResults' => $this->semknoxBaseApi->contentSearchResults   	     
   	 );
   	 if ($total==0) { return false; }
        $result = new SearchBundle\ProductNumberSearchResult(
           $products,
           intval($total),
           []
        );
	  return $result;
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
