<?PHP
	namespace semknoxSearch\Bundle\SearchBundle;	
	use semknoxSearch\Bundle\SearchBundle\semknoxItemData; 
	/**
	*	Klasse für Itemlist, enthält Liste der Items, da Updatefunktion Richtung Semknox-Server mit Listen arbeitet
	*/
	class semknoxItemDatalist {
	    /**
	     * CSV-Delimiter für CatPath-Variable.
	     * default: '|'.
	     *
	     * @var string
	     */ 
		private $CSV_CatPathDelimiter = '|';
		/**
		 * Delimiter für CatPath-Variable.
		 * default: '#'.
		 *
		 * @var string
		 */
		private $CatPathDelimiter = '#';
		/**
		 * Liste mit semknox_itemdata-Elementen.
		 * standard leer.
		 *
		 * @var semknoxItemData
		 */
		public $list = array(); 
		/**
		 * Daten-Dummy
		 * standard array()
		 *
		 * @var array
		 */		
		private $dummy = array();
		/**
		 * Konstruktor der Klasse
		 * @return void
		 */
		public function __construct () {
			$this->list=array();
		}
		/**
		*	fügt ein Item der Liste hinzu, wenn es valid ist.
		*	@param	\semknoxSearch\Bundle\SearchBundle\semknoxItemData $item 	zu prüfendes Item
		*   @return integer
		*/
		public function addItem(semknoxItemData $item) {
			$ret=0;
			if ($item->isValid()) { $this->list[]=$item; $ret=1;}
			return $ret;
		}
		/**
		*	...brauchen wir wohl eher nicht...
		*	@param	\semknoxSearch\Bundle\SearchBundle\semknoxItemData $item 	zu löschendes Item
		*/
		public function removeItem(semknoxItemData $item) {
		}
		/**
		*	lösche Itemliste
		*/
		public function clearList() {
			$this->list=array();
		}
		/**
		* gibt (z.b. für Batch-Prozesse) eine leere itemData-Struktur zurück,
		* @return array 
		*/
		public function getItemDataDummy() {
			if (empty($this->dummy)) {
				$r=new semknoxItemData();
				$this->dummy = $r->getItemDataDummy();
			}
			return $this->dummy;
		}
		/**
		*	transformiert array externer CSV-Struktur in itemData und fügt Liste hinzu. 
    	*   (hier entsprechend Ziegler Masterfeed-Export/FeedDynamix)
	    *   @param array $item
		*   @return void
		*/
		private function csv_addItem(array $item) {
			if (trim($item['ArticleNumber'])=="") {return;}
			$a=new semknoxItemData();
			$a->name=$item['Title'];
			$a->articleNumber=$item['ArticleNumber'];
			$a->image=$item['ImageURL'];
			$a->description=$item['Description'];
			$a->category=str_replace($this->CSV_CatPathDelimiter,$this->CatPathDelimiter,$item['CategoryPath']);
			$a->ean="";
			$a->groupId=$item['GroupId']; 
			$a->appendOnly=false;
			$a->accessories=array(); 
			if (trim($item['Accessories'])!='') {
				$a->accessories=explode(";", $item['Accessories']); 
			}
			$a->addPassOn('shippingTime', $item['ShippingTime']);
			$a->addPassOn('shippingCosts', $item['ShippingCosts']);
			$a->addPassOn('availability', $item['Availability']);
			$a->addPassOn('pseudoprice', $item['PseudoPrice']);
			$a->addPassOn('articleID', $item['MasterArticleNumber']);
			$a->deeplink=$item['Deeplink'];
			$a->price=$item['Price'];
			$a->manufacturer=$item['Manufacturer'];
			$a->rankingImportance=intval($item['Position']);
			$h=trim($item['Attributes']);
			if ($h!='') {
				$m=explode(";", $h);
				foreach($m as $me) {
					$h=explode(':',$me);if (count($me==2)) {
							$a->addAttribute($h[0],$h[1]);
					}
				}					
			}			
			$this->addItem($a);				
		}
		/**
		 * lädt CSV-Datei und fügt Inhalt in Liste ein.
		 * @param string $csvdatei
		 * @return void
		 */
		public function csv_getData($csvdatei) {
			$this->clearlist();
			if (file_exists($csvdatei)) {
				$csv=$this->csv_to_array($csvdatei,';');
				foreach($csv as $item) {					
					$this->csv_addItem($item);
				}
			}
		}
		/**
		 * Wandelt CSV-Inhalt in Array um.
		 * @param string $filename
		 * @param string $delimiter
		 * @return array
		 */
		private function csv_to_array($filename='', $delimiter=',')
		{
		    if(!file_exists($filename) || !is_readable($filename))
		        return array();
		    $header = NULL;
		    $data = array();
		    if (($handle = fopen($filename, 'r')) !== FALSE)
		    {
		        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
		        {
		            if(!$header)
		                $header = $row;
		            else
		                $data[] = array_combine($header, $row);
		        }
		        fclose($handle);
		    }
		    return $data;
		}
        /**
         * gibt die Liste der Items als json-Formatierten String zurück.
         * @return string
         */
		public function getJsonList() {
			$ret=array();
			$c=0;
			foreach ($this->list as $item) {
				$c++;
				$ret[]=$item->itemData;
			}
			return json_encode($ret);
		}
		/**
		 * returning json-formatted string for sitesearch-api-update-call
		 * @return string
		 */
		public function getProductJsonList() : string
		{
		    $ret = [];$ret['products']=[];
		    foreach($this->list as $prod) {
		        $ret['products'][] = $prod->_asSemknoxApiV3Array();
		    }
		    return json_encode($ret);
		}
		/**
		 * returning json-formatted string for sitesearch-api-update-call
		 * using blocksize to split data in blocks and setting next for next blockstart
		 * @return string
		 */
		public function getProductJsonListBlock($maxSize, &$next) : string
		{
		    $ret = [];$ret['products']=[];
		    $i=0; $ms=0;$li=0;
		    foreach($this->list as $prod) {
		        if ($i < $next) { $i++; continue; }
		        if ($li >= $maxSize) { $ms=1; break; }
		        $ret['products'][] = $prod->_asSemknoxApiV3Array();
		        $i++;
		        $li++;
		    }
		    if ($ms) { $next = $i; } else { $next = -1; }
		    if (count($ret)>0) {
		        return json_encode($ret);
		    } else {
		        return "";
		    }
		}
	}
?>
