<?PHP
namespace semknoxSearch\Bundle\SearchBundle;
use \semknoxSearch\Bundle\SearchBundle\semknoxItemDatalist;
	/* Start Klasse Semknox-Base-API */
	class semknoxBaseApi {
	    /**
	     * Base-URL für Abfragen bei Semknox. 
	     * z.b. http://dev-api.semknox.com/
	     *
	     * @var string
	     */ 
	    private $logFile = ''; 
		private $base = "";		
		private $customerID = "";	/**< Kundennummer bei Semknox. */
		private $apiKey = "";	/**< API-Key für Abfragen bei Semknox. */
		private $SessionID = ""; /**< SessionID von User-Session. */
		private $cURL;	/**< zentrales CURL-Objekt für Abfragen von Semknox. */
		public $debugMode = 0;
		public $debugTimes = array();
		public $shopControllerString=''; 
		public $sourceParam= 0; 
		private $queryId=''; /**< Query-ID-Parameter von Semknox. */
		private $expires=0; /**< expires-Parameter von Semknox. */
		public  $groupIdList=array(); /**< enthält die Zuordnung von GroupID zu ArtikelID in searchResults. Form:  [groupIdList] => Array ( [516931450] => Array ( [articles] => Array ( [0] => 183.203 [1] => 183.208 [2] => 183.203A ) ) [517051294] => Array ( [articles] => Array ( [0] => 271.467 [1] => 271.468 ) ) [516994839] => Array ( [articles] => Array ( [0] => 773.007 [1] => 773.005 [2] => 773.011 [3] => 773.009 ) )) */
		private $resultCode=0; /**< wenn Wert<0 Fehler bei Abfrage */
		private $searchmode=0; /**< Suchmodus 0=QueryFilterOrder 1=Query 11=QuerySuggests */
		public	$suggests=array(); /**< suggest-Parameter bei Suche nach Vorschlägen. Array der Form [Suchvorschläge]=>... [Produkte]=>... [Kategorien]=>... jeweils mit Arrays der Form [name]=>...[image]=>...[articleNumber]=>... */
		public  $contentSearchResults=array(); /** Array mit Results aus Content-Suche */
		public  $customerProperties=array(); /**< customerProperties von Semknox, interne Daten-Konfiguration, z.b. zum Verändern der Head-Werte */
        const METHODE_GET    = 'GET';
        const METHODE_PUT    = 'PUT';
        const METHODE_POST   = 'POST';
        const METHODE_DELETE = 'DELETE';
        protected $validMethods = array(
            self::METHODE_GET,
            self::METHODE_PUT,
            self::METHODE_POST,
            self::METHODE_DELETE
        );		
		public $errorcode = 0;		/**< Fehlercode. */
		public $errorText = '';		/**< Fehlertext. */
		public $maxResults = 10;	/**< Anzahl der max. zu liefernden Ergebnisse pro Seite. */
		public $maxSuggests = 10;	/**< Anzahl der max. zu liefernden Suggests. */
		public $order = array();			/**< Liste mit Sortiermöglichkeiten. Enthält eine Liste der Form { ([id] => 0 [viewName] => Produktname) }*/ 
		public $filters = array();		/**< Liste mit möglichen Filtern. Enthält eine Liste der Form {                (
                    [logic] =&gt; AND
                    [id] =&gt; 860
                    [viewName] =&gt; Preis
                    [unitName] =&gt; EUR
                    [type] =&gt; RANGE
                    [position] =&gt; 16
                    [autofill] =&gt; 
                    [options] =&gt; Array
                        (
                        )
                    [min] =&gt; 22.5
                    [max] =&gt; 2527
                    [step] =&gt; 10
                    [idName] =&gt; cost.PRICE
                )} */
		public $searchResults = array();	/**< Ergebnisliste mit allen Infos zu Artikeln vom Semknox. */
		public $interpretedQuery = array();	/**< bei Semknox 'angekommene' Query */
		public $filterSet = array();	/**< Liste mit gesetzten Filtern. Enthält eine Liste der Form             
								[FILTERID] => Array
                (
                    [optionsSet] =&gt; Array
                        (
                            [0] =&gt; OPTIONSID
                        )
                ) */
		public $orderSet = array();		/**< Liste mit gesetzter Sortierreihenfolge. Enthält eine Liste der Form [ORDERID] => ASC */
		public $logdata = '';	/**< String mit Log-Infos */
		public $processingTime=0;	/**< Anzahl ms für Query bei Semknox. */
		public $explanation='';	 /**< erklärender Text zu Suchergebnissen. */
		public $confidence=0;		/**< confidence-Parameter von Semknox. */
		public $category='';		/**< Kategorie zu Suche. */
		public $normalizedCategory='';	/**< normlisierte Kategorie zu Suche. */
		public $corrected='';		/**< korrigierte Suche. */
		public $tags=array();		/**< tags-Parameter von Semknox. */
		public $redirectResult="";	/**< redirect-Result-Parameter von Semknox. Wenn gesetzt, soll Suchergebnisseite direkt weitergeleitet werden. */
		public $useGroupedResults=0;	/**< nutze gruppierte Resultate für die Anzeige. */
		public $useHeadResultsOnly=0;	/**< zeige nur Head-Results an. Es werden nur die Resultate ausgegeben, die durch head=1 gesetzt haben. */
		public $resultsAvailable=0;		/**< Anzahl der Artikel in den Suchergebnissen. */
		public $groupedResultsAvailable=0; /**< Anzahl der Artikelgruppen in den Suchergebnissen. */
		public $headResultsAvailable=0;	/**< Anzahl der Artikel mit head=1 in den Suchergebnissen. */
		public $groupedHeadResultsAvailable=0; /**< Anzahl der Artikelgruppen mit Artikeln mit head=1. */
		/**
		*	Konstruktor-Methode für Objekt Semknox. Speichert die Konfigurationsparameter und erstellt zentrales Curl-Objekt.
		* @param	$base	(string)	Basis-URL für Semknox-Abfragen. z.b. http://dev-api.semknox.com/
		* @param	$customerID	(string)	Kundennummer bei semknox
		*	@param	$apiKey	(string)	API-Schlüssel für Abfragen bei semknox
		*	@param	$SessionID	(string)	Session-ID von User
		* @param	$useGroupResults	(int)	0=gib Artikellisting zurück 1=gib Artikelgruppenlisting zurück
		*	@param	$useHeadResultsonly (int)	0=gib alle Resultate zurück 1=gib nur head=1-Resultate zurück
		*/
		public function __construct($base, $customerID, $apiKey, $sessionID,$useGroupedResults=0,$useHeadResultsonly=0) {
			$this->base = rtrim($base, '/') . '/';
			$this->customerID=$customerID;
			$this->apiKey=$apiKey;
			$this->SessionID=$sessionID;			
			$this->useGroupedResults=$useGroupedResults;
			$this->useHeadResultsOnly=$useHeadResultsonly;
        $this->cURL = curl_init();
		}	
		/**
		* gibt die Anzahl der Order-Felder aus der Suche zurück
		*/
		public function getOrderCount() {
			return count($this->order);
		}
		/**
		* gibt die Anzahl der Filter-Felder aus der Suche zurück
		*/
		public function getFilterCount() {
			return count($this->filters);
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
		* gibt je nach Eintellungen die max. Anzahl der Suchergebnisse zurück (headonly, grouped/non-grouped)
		*/
		public function getSearchResultsCount() {
			if ($this->useHeadResultsOnly) {
					if ($this->useGroupedResults) {
						return $this->groupedHeadResultsAvailable;
					} else {
						return $this->headResultsAvailable;
					}
			} else {
					if ($this->useGroupedResults) {
						return $this->groupedResultsAvailable;
					} else {
						return $this->resultsAvailable;
					}
			}
		}
		/**
		* gibt einzelne Artikelliste des Suchergebnisses zurück.
		*	@params $productsonly	(int)	=1, dann wird nur der erste Artikel einer Artikelgruppe zurückgegeben
		*	@params $preferPrimary (int)	=1, dann wird versucht, den Primär-Artikel einer Gruppe zu finden und dieser wird als erster zurückgegeben
		*/
		public function getSearchResults($productsonly=0, $preferPrimary=0) {
			$ret=array();$cp=0;
			foreach($this->searchResults as $res) {
				$cp++; $c=0;$lastid='';
				$res2=$res;
				if ($preferPrimary) {
				    foreach($res as $item) {
				        if ($item['articleNumber']==$item['groupId']) {
				            array_unshift($res2,$item);
				        } else {
				            $res2[]=$item;
				        }
				    }
				}
				foreach ($res2 as $item) {
					if ( ($this->useGroupedResults) && ($c > 0) && ($lastid!='') ) {
						$ret[$lastid]['altarticles'][]=$item;
					} else {
						$item['altarticles']=array();
						$ret[$item['id']]=$item;					
						$lastid=$item['id'];
					}
					if ($productsonly) { break; }
					$c++;
				}
			}
			return $ret;
		}
		/*
		*	Prüft, ob Head-Eintrag gesetzt ist, falls useHeadonly aktiv, gibt 1 zurück, wenn o.k. 0 sonst
		*/
		private function checkHead($itemHead) {
			$ret=1;			
			if ($this->useHeadResultsOnly) {
				if ($itemHead!=1) { $ret=0; }
			}
			return $ret;
		}
		/**
		* zieht aus CURL-Antwort Daten für interne Felder
		*/
		public function processResults($result) {
			$this->filters=array();$this->order=array();$this->searchResults=array();$this->redirectResult='';
			$this->filtersSet=array();$this->orderSet=array();$this->processingTime=0;$this->queryId='';$this->expires=0;
			$this->interpretedQuery=array();$this->resultsAvailable=0;$this->groupIdList=array();
			$this->groupedResultsAvailable=0;$this->groupedHeadResultsAvailable=0;$this->headResultsAvailable=0;
			if ($this->searchmode==11) {
				$this->suggests=$result['suggests'];
				return;
			}
			if (isset($result['contentSearchResults'])) { $this->contentSearchResults = $result['contentSearchResults']; }
			if ( ($this->resultCode<0) || (!is_array($result)) ) {
				return;
			}
			if (isset($result['redirect'])) { $this->redirectResult=$result['redirect'];}
			if (isset($result['orderSet'])) { $this->orderSet=$result['orderSet'];}
			if (isset($result['filtersSet'])) { $this->filtersSet=$result['filtersSet'];}
			if (isset($result['processingTimeMs'])) { $this->processingTime=$result['processingTimeMs'];}
			if (isset($result['queryId'])) { $this->queryId=$result['queryId'];}
			if (isset($result['order'])) { $this->order=array_merge(array(0=>array('id'=>-1,'viewName'=>'Relevanz')), $result['order']);}
			if (isset($result['filters'])) { $this->filters=$result['filters'];}		
			if (isset($result['searchResults'])) { 
				$this->searchResults=array();
				$grid=1;
				foreach($result['searchResults'] as $k => $res) {
					foreach ($res as $item) {						
						if ( !((is_array($result['filtersSet'])) && (count($result['filtersSet'])>0)) && ($this->checkHead($item['head'])!=1) ) { continue;}
						if (!isset($this->searchResults[$k])) { $this->searchResults[$k]=array(); }
						$this->searchResults[$k][]=$item;
						if (isset($item['groupId'])) { 
							if ($this->useGroupedResults==0) { $hgr=$grid; } else {$hgr=$item['groupId'];}
						} else {
							 $hgr=$grid; 
						}
						if (!is_array($this->groupIdList[$hgr])) { $this->groupIdList[$hgr]=array();$this->groupIdList[$item['groupId']]['articles']=array();}
						$this->groupIdList[$hgr]['articles'][]=$item; 
						$grid++;
					}
				}
			}			
			if (isset($result['resultsAvailable'])) { $this->resultsAvailable=$result['resultsAvailable'];}			
			if (isset($result['headResultsAvailable'])) { $this->headResultsAvailable=$result['headResultsAvailable'];}			
			if (isset($result['groupedResultsAvailable'])) { $this->groupedResultsAvailable=$result['groupedResultsAvailable'];}			
			if (isset($result['groupedHeadResultsAvailable'])) { $this->groupedHeadResultsAvailable=$result['groupedHeadResultsAvailable'];}			
			if (isset($result['interpretedQuery'])) { 
				$this->interpretedQuery=$result['interpretedQuery'];
				if (isset($this->interpretedQuery['explanation'])) { $this->explanation=$this->interpretedQuery['explanation'];}
				if (isset($this->interpretedQuery['confidenc'])) { $this->confidence=$this->interpretedQuery['confidenc'];}
				if (isset($this->interpretedQuery['category'])) { $this->category=$this->interpretedQuery['category'];}
				if (isset($this->interpretedQuery['normalizedCategory'])) { $this->normalizedCategory=$this->interpretedQuery['normalizedCategory'];}
				if (isset($this->interpretedQuery['corrected'])) { $this->corrected=$this->interpretedQuery['corrected'];}
				if (isset($this->interpretedQuery['tags'])) { $this->tags=$this->interpretedQuery['tags'];}
			}
		}
		/**
		 * schreibt logData in eine Datei, sofern Datei hinterlegt ist
		 */
		private function writeLogFile() {
		    $h=trim($this->logFile);
		    if ($h) {
		        $d="\n[".date("Y-m-d H:i:s")."] ".$this->shopControllerString."\n";
		        file_put_contents($h, $d, FILE_APPEND);
		        file_put_contents($h, $this->logdata, FILE_APPEND);
		    }
		}
		/**
		*	prüft Rückgabe von Semknox auf Fehler, startet processResults
		*/
    protected function prepareResponse($result, $httpCode) {
    		$this->resultCode=0;
        $this->logdata.="<h2>HTTP: $httpCode</h2>\n";
        if (null === $decodedResult = json_decode($result, true)) {
            $jsonErrors = array(
                JSON_ERROR_NONE => 'Es ist kein Fehler aufgetreten',
                JSON_ERROR_DEPTH => 'Die maximale Stacktiefe wurde erreicht',
                JSON_ERROR_CTRL_CHAR => 'Steuerzeichenfehler, möglicherweise fehlerhaft kodiert',
                JSON_ERROR_SYNTAX => 'Syntaxfehler',
            );
            /*
            echo "<h2>Could not decode json</h2>";
            echo "json_last_error: " . $jsonErrors[json_last_error()];
            echo "<br>Raw:<br>";
            echo "<pre>" . print_r($result, true) . "</pre>";
            */
            $this->resultCode=-1;
        }
        if ($this->debugMode>10000) {            
            echo "\n semknox:resultsAvailable:".$decodedResult['resultsAvailable'];
            echo "\n semknox:limit:".$decodedResult['limit'];
            echo "\n semknox:searchResults:".count($decodedResult['searchResults']);
            echo "\n semknox:processingTimeMs:".$decodedResult['processingTimeMs'];
        }
       	$this->processResults($decodedResult);
       	return $decodedResult;
    }
    /**
    * führt den eigentlichen Call zum Semknox-Server aus.
    * GET: alle Parameter kommen in params-array
    * POST: alle Parameter kommen in data-Array, Felder müssen json-encoded übermittelt werden
    * @params	$url	(string)	URL für Call
    * @params $method	(string) Methode für Abfrage (GET,POST,DELETE)
    * @params $params (array)	Parameter für Abfrage in Feld der Form ([KEY]=>[VALUE])
    * @params $data	(array)	Data-Parameter für Abfrage in Feld der Form ([KEY]=>json_encoded(VALUE))
    */
		private function call($url, $method = self::METHODE_GET, $params=array(), $data=array()) {
			$this->logdata.=var_export($method,true);
			$queryString = '';$dataString='';
			$params['sessionId']=$this->SessionID;$params['customerId']=$this->customerID;$params['apiKey']=$this->apiKey;
            if (!empty($data)) {
      	       if ( ($method == self::METHODE_POST) || ($method == self::METHODE_PUT) ) {
					$data['sessionId']=$this->SessionID;$data['customerId']=$this->customerID;$data['apiKey']=$this->apiKey;
					unset($params['sessionId']);unset($params['customerId']);unset($params['apiKey']);unset($params['full']);
			   }
            $dataString = http_build_query($data);
            }
            $queryString = http_build_query($params);
            $url = rtrim($url, '?') . '?';
			$url .=  $queryString; 
			$url = rtrim($url, '?');
			$this->logdata.=var_export($url,true);
			$this->logdata.=var_export($dataString,true);
			$this->cURL = curl_init();
            $opt=array(CURLOPT_RETURNTRANSFER => 1,
      					 CURLINFO_HEADER_OUT=>true,
								 CURLOPT_URL => $url,
      					 CURLOPT_CUSTOMREQUEST => $method);
      		if ($dataString) {
     		     $opt[CURLOPT_POSTFIELDS]=$dataString;      
     	    }
     	    curl_setopt_array($this->cURL,$opt);
     	    $header=array();
     	    curl_setopt($this->cURL, CURLOPT_HTTPHEADER, $header);     	    
     	    $result   = curl_exec($this->cURL);     	    
     	    if ($this->debugMode>0) {            	    			        
     	        $dca=array('total_time', 'namelookup_time', 'connect_time', 'pretransfer_time', 'starttransfer_time');
     	        $h=curl_getinfo($this->cURL);
     	        $this->debugTimes['curl_data']=array();
     	        $this->debugTimes['curl_data'][]=array(0, $url);
     	        foreach ($dca as $k) {
     	            $this->debugTimes['curl_data'][]=array($h[$k],$k);
     	        }
     	    }
     	    $httpCode = curl_getinfo($this->cURL, CURLINFO_HTTP_CODE);        
            curl_close($this->cURL);
            $this->writeLogFile();
            $h=$this->prepareResponse($result, $httpCode);
            return $h;
		}
		/**
		*	Ruft die Standard-Suche von Semknox auf, derzeit nicht benutzt, da keine Filter/Order-Funktion möglich.
		*/
		public function QuerySearchResults($searchstr, $offset=0, $limit=1000000) {
			$this->searchmode=1;
			$params=array();
			$params['query']=$searchstr;
			$params['offset']=$offset;
			$params['limit']=$limit;
			$q = $this->base."products?";
			$ret = $this->call($q, self::METHODE_GET, $params);
			$this->logdata.=print_r($ret,true);			
		}
		/**
		*		Standard-Suche mit Sortierung, Filter und Offsets.
		*		@param	$searchstr	(string)	zu suchender Text
		*		@param	$orderid	(int)	Sortierreihenfolge aus Order-Liste
		*		@param	$orderdirection	(string)	Sortier-Reihenfolge ASC oder DESC 
		*		@param	$filter	(array)	Filter-array
		*		@param	$offset	(int)	Offset für Start der Ausgabe
		*		@param	$limit	(int)	max. Anzahl für Ausgabe pro Seite
		**/
		public function QuerySearchResultsOrderby($searchstr, $orderid=-1, $orderdirection='ASC', $filter, $offset=0, $limit=1000000) {
			$this->searchmode=0;
			$params=array();$data=array();
			if ($this->useGroupedResults) {
				$data['groupResults']="true";			
			} else {
				$data['groupResults']="false";
			}
			$data['query']=$searchstr;
			$data['offset']=$offset;
			$data['limit']=$limit;
			switch ($this->sourceParam) {
			    case 1   : $data['source'] ='SHOPWARE-Prefiltering';
			    case 2   : $data['source']='SHOPWARE_404';
			}
			$f=array();
			foreach($filter as $k => $v) {
				if (!is_array($v)) {continue;}
				if (count($v)) {
					if ( (isset($v['min'])) || (isset($v['max'])) ) {
						/*
						if (trim($v['min']=='')) { $v['min']=0;}
						if (trim($v['max']=='')) { $v['max']=0;}
						if ($v['min']==$v['max']) { continue;}
						$v['min']=floatval($v['min']);$v['max']=floatval($v['max']);
						if ($v['min']>=$v['max']) { continue; }
						*/
						$e=array();$e['id']=$k;
						if (isset($v['min'])) { $e['minValue']=floatval($v['min']); }
						if (isset($v['max'])) { $e['maxValue']=floatval($v['max']); }
						if (isset($v['unitName'])) { $e['unitName']=$v['unitName']; }
						$f[]=$e;
					} else {
						$e=array();$e['id']=$k;$e['values']=array();
						foreach ($v as $vv) {
							$e['values'][]=intval($vv);
						}
						$f[]=$e;
					}
				}
			}
			$data['filters']=json_encode($f);
			if ($orderid>-1) {
				$data['order']=json_encode(array("id"=>$orderid, "direction"=>$orderdirection));
			}
			$q = $this->base."products/search?";
			$ret = $this->call($q, self::METHODE_POST, $params, $data);
		}
		/**
		*		holt Daten für vorschlagende Suche
		*	  ???todo
		**/		
		public function QuerySuggests($searchstr, $limit, $language='de', $suggestCatRelations=null) {
			$this->searchmode=11;
			$params=array();
			$params['query']=$searchstr;
			$params['limit']=100;
			$params['limitProduct']=$limit;
			$params['language']=$language;
			$q = $this->base."queries/semanticSuggest?";
			$ret = $this->call($q, self::METHODE_GET, $params);
			$news = [];
			if ( (is_array($suggestCatRelations)) && (count($suggestCatRelations) > 0) ) {
			    foreach($this->suggests as $cat => $items) {
			        if ($suggestCatRelations[$cat]) {
			            $news[$suggestCatRelations[$cat]] = ['title'=>$cat, 'items'=>$items];
			        }
			    }
			} else {
			    foreach($this->suggests as $cat => $items) {
			        $news[$cat] = ['titel'=>$cat, 'items' => $items];
			    }
			}
			$this->suggests = $news;
		}
		/**
		*		löscht Artikel mit übergebener ID
		*	@params $id	(string)	Artikel-ID des Artikels der gelöscht werden soll
		*/
		public function DeleteArticle($id) {
			if (trim($id)=='') { return; }
			$params=array();
			$params['articleNumber']=$id;
			$q = $this->base."products?";
			$ret = $this->call($q, self::METHODE_DELETE, $params);		
			return $ret;
		}
		/**
		 * starte Batchupload-Prozess
		 * @return mixed
		 */
		public function startBatchUpload() {
		    $params=array();
		    $q = $this->base."products/clearInput?";
		    $ret = $this->call($q, self::METHODE_GET, $params);
		    return $ret;		    
		}
		/**
		 * beende Batchupload-Prozess.
		 * alle bisher geschriebenen Artikel werden aktualisiert, alle nicht vorhandenen werden gelöscht
		 * @return mixed
		 */
		public function finishBatchUpload() {
		    $params=array();
		    $q = $this->base."products/fullUpdate?";
		    $ret = $this->call($q, self::METHODE_GET, $params);
		    $ret['resultText']='';
		    switch(strtolower($ret['status'])) {
		        case 'success' :
		            $ret['resultCode']=1;
		            $ret['resultText']="Produkte wurden hochgeladen\n";
		            $ret['resultText'].="Die Verarbeitung erfolgt in ca. ".$ret['estimated_update_time']."\n";
		            break;
		        default: $ret['resultCode']=-1;
		    }
		    return $ret;		    
		}
		/**
		 * sende Datenblock.
		 * @params	$inpdata	(semknoxItemDataList)
		 * @return mixed
		 */
		public function sendBatchData($inpdata) {
		    $data=array();$params=array();
		    $data['productsJsonArray']=$inpdata->getJsonList();
		    $q = $this->base."products/batchInput";
		    $ret = $this->call($q, self::METHODE_PUT, $params, $data);
		    $ret['resultText']='';
		    switch(strtolower($ret['status'])) {
		        case 'success' :
		            $ret['resultCode']=1;
		            $ret['resultText']=$ret['nr_of_products']." Produkte wurden verarbeitet\n";
		            $ret['resultText'].="Die Verarbeitung erfolgt in ca. ".$ret['estimated_update_time']."\n";
		            break;
		        default: $ret['resultCode']=-1;
		    }
		    return $ret;		    
		}
		public function sendCatData($inpdata) {
		    $data=array();$params=array();
		    $data['ccssJson']=json_encode($inpdata);
		    $q = $this->base."queries/updateCcss";
		    $ret = $this->call($q, self::METHODE_POST, $params, $data);
		    $ret['resultText']='';		    
		    switch(strtolower($ret['status'])) {
		        case 'success' :
		            $ret['resultCode']=1;
		            $ret['resultText']="Kategoriedaten wurden verarbeitet\n";
		            break;
		        default: $ret['resultCode']=-1;
		    }
		    return $ret;		    
		}
		/**
		*		Aktualisiert Liste der Artikel bei Semknox
		*		@params	$inpdata	(semknoxItemDataList)
		*		@params $full	(int) 0=aktualisiere nur Artikel 1=aktualisiere alle Artikel aus Liste, lösche alle anderen Artikel, die nicht in Liste stehen
		*/
		public function UpdateArticleList($inpdata, $full=0) {
			$data=array();$params=array();
			$data['productsJsonArray']=$inpdata->getJsonList();
			if ($full) {
				$data['full']="true";
			} else {
				$data['full']="false";
			}
			$q = $this->base."products";
			$ret = $this->call($q, self::METHODE_PUT, $params, $data);
			$ret['resultText']='';
			switch(strtolower($ret['status'])) {
				case 'success' : 
						$ret['resultCode']=1;
						$ret['resultText']=$ret['nr_of_products']." Produkte wurden verarbeitet\n";
						$ret['resultText'].="Die Verarbeitung erfolgt in ca. ".$ret['estimated_update_time']."\n";
						break;
				default: $ret['resultCode']=-1;
			}
			return $ret;
		}		
		/**
		*	Startet Datenfeed-Import, generiert itemDataList und lädt diese Daten zu Semknox hoch
		*/
		public function UpdateArticlesFromMasterfeed($feed) {
		    $itemDataList = new semknoxItemDataList;
			$itemDataList->csv_getData($feed);
			$this->UpdateArticleList($itemDataList,1);
		}
		private function initCustomerProperties() {
			$props = array("sxSuggestLabelCategories", "sxSuggestLabelBrands", "sxSuggestLabelSearch", "sxSuggestLabelProducts", "sxSuggestLabelContent", "sxSuggestLabelCategoriesAndFeatures", "sxSuggestMetaForProducts", "sxSuggestIgnoreProductsForCategory", "sxSuggestProductImagesCategorySuggests", "sxContentUrlParams", "sxGroupSearchSuggests", "sxGroupSearchSuggestMultiplier", "sxSuggestCategoryMappingAutoGeneration", "sxAddRelatedProductsBelow", "sxContentResults", "sxOrderOptionsIgnore", "sxSearchTail", "sxPartialArticleNumberMinLength", "sxZeroResultStrategy", "sxCurrency", "sxIndexOrderNumber", "sxDisableSpellingCorrection", "Ranking");
			$this->customerProperties=array();
			foreach ($props as $p) { $this->customerProperties[$p]=array(); }
		}
		public function getCustomerProperties() {
				$data=array();$params=array();
				$this->initCustomerProperties();
				$q=$this->base."customers/".$this->customerID."/properties/complete";
				$ret = $this->call($q, self::METHODE_GET, $params, $data);
				foreach($ret['properties'] as $k => $v) {
					$this->customerProperties[$k] = $v;
				}		
				return $this->customerProperties;
		}
		public function setCustomerProperties($properties) {
				$data=array();$params=array();
				$this->initCustomerProperties();
				$d=array();
				foreach($properties as $k => $v) {
					if (array_key_exists($k, $this->customerProperties)) {
						if (!is_array($v)) { $v = array($v); }
						foreach($v as &$e) {
							$e=(string)$e;
							unset($e);
						}
						$d[$k]=$v;
					}
				}
				if (count($d)) {
					$d=array('properties' => $d);
					$data['properties']=json_encode($d);
					$q=$this->base."customers/".$this->customerID."/properties";
					$ret = $this->call($q, self::METHODE_POST, $params, $data);
				}
		}
	}	
	/**Ende Klasse Semknox */	
?>
