<?PHP
	namespace semknoxSearch\Bundle\SearchBundle;	
	/**
	*		Klasse zum Verwalten einzelner Items für Update und Suche
	*		mit * markierte Felder sind Pflichtfelder
	* 	Nutzung: 
	*		A) Objekt generieren und direkt ein itemData-Array übergeben
	*		B) Objekt generieren und über Setter-Methoden die entsprechenden Werte übergeben
	* 	über Funktion isValid() prüfen, ob aktuelle itemData alle Pflichtfelder besitzt, wird automatisch ausgeführt, wenn itemData bei constructor übergeben wird
	*/
	class semknoxItemData {
	    /**
	     * Cat-Path-Delimiter. Standard: '#'
	     * @var string
	     */
		private $CatPathDelimiter = '#';
		/**
		 * ItemData-Dummy-Array-Keys. 
		 * @var array
		 */
		private $itemData_dumk = array();
		/**
		 * ItemData-Dummy-Daten.
		 * @var array
		 */
		private $itemData_dummy = array(
						'name'=>''						
						,'articleNumber'=>''	
						,'image'=>''					
						,'description'=>''		
						,'category'=>''				
						,'ean'=>''						
						,'groupId'=>''				
						,'appendOnly'=>false	
						,'accessories'=>array() 
						,'passOn'=>array() 		
						,'deeplink'=>''				
						,'price'=>''					
						,'manufacturer'=>''		
						,'rankingImportance'=>''	
						,'attributes'=>array()		
		                ,'secondaryCategories'=>array()		
		                ,'seoCategories'=> array() 
		                ,'useOnlySEOCats'=> 0 
		);
		/**
		 * Item-Data-Array.
		 * @var array
		 */
		public $itemData = array();
		/**
		 * Konstruktor, erstellt interne Datenstruktur.
		 * wird itemdata bereits komplett übergeben, dann werden diese Daten übernommen, sofern sie valid sind, 
		 * Rückgabewert 0, wenn keine Daten übernommen wurden, wenn itemData übernommen wurde, dann Rückgabewert 1
		 * @param array $itemdata
		 * @return number
		 */
		public function __construct($itemdata=array()) {
			$this->itemData = $this->itemData_dummy;
			$this->itemData_dumk = array_keys($this->itemData_dummy);
			if (!empty($itemdata))  { return $this->saveItem(itemdata); } else {return 0;}
		}
		/**
		 * returns product-data as semknox-api-array for upload to api v3
		 * @return array
		 */
		public function _asSemknoxApiV3Array() : array
		{
		    $ret=[];
		    $ret['identifier'] = $this->itemData['articleNumber'];
		    $ret['groupIdentifier'] = $this->itemData['groupId'];
		    $ret['name'] = $this->itemData['name'];
		    $ret['productUrl'] = $this->itemData['deeplink'];
		    $ret['categories'] = $this->getApiV3CatNames();
		    $ret['image'] = $this->itemData['image'];
		    if (trim($ret['image']) == '') { unset($ret['image']); }
		    $ret['attributes'] = $this->getApiV3SemknoxAttributes();
		    return $ret;
		}
		/**
		 * returning interenal categorydata for sitesearch-api-call
		 * @return array
		 */		
		private function getApiV3CatNames() : array
		{
		    $ret=[];
		    if (trim($this->itemData['category']) == '') { return $ret; }
   		    $cData[] = trim($this->itemData['category']);
   		    if (is_array($this->itemData['secondaryCategories'])) {
   		        foreach($this->itemData['secondaryCategories'] as $c) {
   		            $cData[]=trim($c);
   		        }
   		    }
		    foreach ($cData as $c) {
		        $cats = explode('#',$c);
		        if (is_array($cats)) {
		            $h=[];
		            foreach ($cats as $cat) {
		                $t=trim($cat);if ($t=='') { continue; }
		                $h[]=$t;
		            }
		            if (count($h)) { $ret[] = ['path' => $h]; }
		        }
		    }
		    return $ret;
		}
		/**
		 * returning properties for sitesearch-api-v3-call
		 * @return array
		 */
		private function getApiV3SemknoxAttributes() : array
		{
		    $ret=[];
		    $ret[] =["key" => "SKU", "value" => "".$this->itemData['articleNumber']];
		    $ret[] =["key" => "price", "value" => "".$this->itemData['price'] ];
		    $ret[] =["key" => "description", "value" => "".$this->itemData['description'] ];
		    $ret[] =["key" => "EAN", "value" => "".$this->itemData['ean'] ];
		    $ret[] =["key" => "manufacturer", "value" => $this->itemData['manufacturer'] ];
		    $ret[] =["key" => "rankingImportance", "value" => "".$this->itemData['rankingImportance']];
		    /*
		    $ret[] =["key" => "manufacturerNumber", "value" => $this->getManufacturerNumber()];
		    $ret[] =["key" => "shippingTime", "value" => "".$this->getDeliveryTime()];
		    $ret[] =["key" => "availability", "value" => "".$this->getStockAsString()];
		    $ret[] =["key" => "inStock", "value" => "".$this->getStock()];
		    $ret[] =["key" => "releaseDate", "value" => date("Y-m-d" , $this->getReleaseDate()->getTimestamp())];
		    $ret[] =["key" => "salesCount", "value" => "".$this->getSalesCount()];
		    $ret[] =["key" => "votingCount", "value" => "".$this->getVotesCount()];
		    $ret[] =["key" => "votingValue", "value" => "".$this->getVotesValue()];
		    $ret[] =["key" => "clickRate", "value" => "".$this->getProductClicks()];
		    */
		    foreach($this->itemData['passOn']  as $key => $val) {
		        $key = $key;
		        if ( (is_null($key)) || (trim($key)=='') ) { continue; }		        
	            $ret[] = [ "key" => $key, "value" => "".$val ];
		    }
		    foreach($this->itemData['attributes']  as $key => $val) {
		        $key = $key;
		        if ( (is_null($key)) || (trim($key)=='') ) { continue; }
		        $ret[] = [ "key" => $key, "value" => "".$val ];
		    }
		    return $ret;
		}
		/**
		 * Testet, ob in item alle Pflichtfelder existieren
		 * @param array $item
		 * @return number
		 */
		private function checkItemData(array &$item) {
			$ret=1;
			if (empty($item['name'])) { $ret=0; }
			if (empty($item['articleNumber'])) { $ret=0; }
			if (!isset($item['price'])) { $ret=0; }
			if (empty($item['deeplink'])) { $ret=0; }			
			return $ret;
		}
		/**
		 * Funktion zum Auffüllen der Werte aus externem ItemData, um falsche Keys zu eleminieren
		 * @param array $item
		 * @return void
		 */
		private function fillValues(array $item) {
			foreach($this->itemData_dumk as $k) {
				if (isset($item[$k])) { $this->itemData[$k]=$item[$k]; }
			}
		}
		/**
		 * zurücksetzen der Daten
		 * @return void
		 */
		public function resetItemData() {
			$this->itemData = $this->itemData_dummy;
		}
		/**
		 * gibt leeres Array mit ItemData-Struktur zurück
		 * @return array
		 */
		public function getItemDataDummy() {
			return $this->itemData_dummy;
		}
		/**
		 * speichert Daten eines Item mit ItemData-Struktur im internen Datenspeicher
		 * @param array $item 
		 * @return number
		 */
		public function saveItem($item) {
			$ret=0;
			if ($this->checkItemData($item)) {
				$this->fillValues($item);
				$ret=1;
			}
			return $ret;
		}		
		/**
		* prüft, ob übergebene Daten Valid sind
		* @return number 1=valid, 0 sonst
		*/
		public function isValid() {
			$ret=0;
			if ($this->checkItemData($this->itemData)) { $ret=1; }
			return $ret;
		}
  	public function __get($property) {
    	if (in_array($property, $this->itemData_dumk)) {
      	return $this->itemData[$property];
    	}
  	}
  	public function __set($property, $value) {
    	if (in_array($property, $this->itemData_dumk)) {
      	$this->itemData[$property] = $value;
    	}
  	}		
		/**
		* fügt ein Zubehör in die Liste der Zubehöre ein. 
		* @param string $v  Artikelnummer
		* @return void
		*/
		public function addAccessorie($v) {
			if (!in_array($v, $this->itemData['accessories'])) { $this->itemData['accessories'][]=$v;}
		}
		/**
		* fügt der Liste der Kategorien einen weiteren Eintrag hinzu.
		* @param string $v Kategorie-Titel
		* @return void
		*/
		public function addCategory($v) {
			if (trim($this->itemData['category'])!='') { $this->itemData['category'].=$this->CatPathDelimiter;}
			$this->itemData['category'].=$v;
		}
		/**
		 * fügt in die Liste der PassOns einen weiteren Wert hinzu bzw. ersetzt diesen, falls key k schon exisitert
		 * @param string $k
		 * @param mixed $v
		 * @return void
		 */
		public function addPasson($k, $v) {
			$this->itemData['passOn'][$k]=$v;
		}
		/**
		 * fügt in die Liste der Attribute einen weiteren Wert hinzu bzw. ersetzt diesen, falls key k schon exisitert
		 * @param string $k
		 * @param mixed $v
		 * @return void
		 */
		public function addAttribute($k, $v) {
			$this->itemData['attributes'][$k]=$v;			
		}
	}
?>
