<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */
namespace semknoxSearch\Bundle\SearchBundle\FacetHandler;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactory;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\PriceHelperInterface;
use Shopware\Bundle\SearchBundleDBAL\FacetHandlerInterface;
use Shopware\Bundle\SearchBundle\Facet;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Components\QueryAliasMapper;
/**
 * @category  Shopware
 * @package   Shopware\Bundle\SearchBundleDBAL\FacetHandler
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class PriceFacetHandler implements FacetHandlerInterface
{
    /**
     * @var string
     */
    private $minFieldName;
    /**
     * @var string
     */
    private $maxFieldName;
    /**
     * @var string
     */
    private $fieldLabel;
    private $activeMin=0;
    private $activeMax=0;
    private $found = false;
		private $pricePropertyId=0;
		private $includelist=array('price', 'Preis', 'Preise', 'prices', 'prix', 'Verkaufspreis', 'salesprice');
    /**
     * @param PriceHelperInterface $priceHelper
     * @param QueryBuilderFactory $queryBuilderFactory
     * @param \Shopware_Components_Snippet_Manager $snippetManager
     * @param QueryAliasMapper $queryAliasMapper
     */
    public function __construct(
        \semknoxSearch\Bundle\SearchBundle\semknoxBaseApiV3 $api
    ) {
        $this->minFieldName = 'semkMin_';
        $this->maxFieldName = 'semkMax_';
    		$this->api=$api;
    		$this->fieldLabel = "Preis";
    }
    /**
     * {@inheritdoc}
     */
    public function supportsFacet(FacetInterface $facet)
    {
        return ($facet instanceof Facet\PriceFacet);
    }
    /**
     * @param FacetInterface|Facet\PriceFacet $facet
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     * @return RangeFacetResult
     */
    public function generateFacet(
        FacetInterface $facet,
        Criteria $criteria,
        ShopContextInterface $context
    ) {
        $queryCriteria = clone $criteria;
        $queryCriteria->resetConditions();
        $queryCriteria->resetSorting();
				$properties = $this->getProperties($this->api->filters);
				if (count($properties)) { $pricedata=$properties[0];} else { return null; }
        $this->getActiveMinMaxPriceFromRequest();
				$version="5.3.0";$operator=">=";
				$v=version_compare( Shopware()->Config()->get( 'Version' ), $version, $operator );
				if ($v) {
	        return new RangeFacetResult(
	            $facet->getName(),
	            $this->found,
	            $pricedata['name'],
	            (float) $pricedata['min'],
	            (float) $pricedata['max'],
	            (float) $this->activeMin,
	            (float) $this->activeMax,
	            "semkMin_".$pricedata['name'],
	            "semkMax_".$pricedata['name'],
	            [],
	            null,
	            2,
	            'frontend/listing/filter/facet-currency-range.tpl'
	        );
				} else {        
	        return new RangeFacetResult(
	            $facet->getName(),
	            $this->found,
	            $pricedata['name'],
	            (float) $pricedata['min'],
	            (float) $pricedata['max'],
	            (float) $this->activeMin,
	            (float) $this->activeMax,
	            "semkMin_".$pricedata['name'],
	            "semkMax_".$pricedata['name'],
	            [],
	            'frontend/listing/filter/facet-currency-range.tpl'
	        );
      	}
    }
    /**
    	get filters from semknox-API-Structure, filtered by properties
    	@param array $filters - filters-structure from semknox
    */    
    protected function getProperties (array $filters) {
    	$props=array();
    	foreach ($filters as $filter) {
    		if ( ($filter['type']!='RANGE') || (! in_array($filter['name'], $this->includelist)) ) {continue;}
    		$props[]=$filter;
    	}
    	return $props;
    }
    public function getActiveMinMaxPriceFromRequest() {
    	$request = Shopware()->Container()->get('front')->Request();
    	$this->found=false;
    	$params = $request->getParams();
    	$c1=strlen($this->minFieldName);
    	$c2=strlen($this->maxFieldName);
    	$minf='';$maxf='';
    	foreach($params as $k => $v) {
    		$s1 = substr($k,0,$c1);
    		$s2 = substr($k,0,$c2);
    		if ($s1==$this->minFieldName) { $minf=$k; $pid=(substr($k,$c1,1000)); if (! in_array($pid, $this->includelist)) { $pid=''; $minf=''; } }    		
    		if ($s2==$this->maxFieldName) { $maxf=$k; $pid=(substr($k,$c1,1000)); if (! in_array($pid, $this->includelist)) { $pid=''; $maxf=''; } }    		
    	}
   		if ($minf) { $this->activeMin=$params[$minf]; $this->found=true; }
   		if ($maxf) { $this->activeMax=$params[$maxf]; $this->found=true;}
   		$this->pricePropertyId=$pid;
    }
}
