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
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetResult\FacetResultGroup;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListItem;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactory;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet;
use Shopware\Bundle\SearchBundleDBAL\FacetHandlerInterface;
use Shopware\Bundle\StoreFrontBundle\Struct;
use Shopware\Bundle\StoreFrontBundle\Gateway\PropertyGatewayInterface;
use Shopware\Components\QueryAliasMapper;
/**
 * @category  Shopware
 * @package   Shopware\Bundle\SearchBundleDBAL\FacetHandler
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class PropertyFacetHandler implements FacetHandlerInterface
{
    /**
     * @var \semknoxSearch\Bundle\SearchBundle\semknoxBaseApiV3
     */
	private $api;
    /**
     * @var string
     */
    private $fieldName;
    /**
     * @param PropertyGatewayInterface $propertyGateway
     * @param QueryBuilderFactory $queryBuilderFactory
     * @param QueryAliasMapper $queryAliasMapper
     */
    public function __construct(
        \semknoxSearch\Bundle\SearchBundle\semknoxBaseApiV3 $api
    ) {
    		$this->fieldName = 'sFilterProperties';
    		$this->fieldName = 'f';
    		$this->api=$api;
    }
    /**
     * {@inheritdoc}
     */
    public function supportsFacet(FacetInterface $facet)
    {
        return ($facet instanceof Facet\PropertyFacet);
    }
    /**
     * @param FacetInterface|Facet\PropertyFacet $facet
     * @param Criteria $criteria
     * @param Struct\ShopContextInterface $context
     * @return FacetResultGroup[]
     */
    public function generateFacet(
        FacetInterface $facet,
        Criteria $criteria,
        Struct\ShopContextInterface $context
    ) {
        $queryCriteria = clone $criteria;
        $queryCriteria->resetConditions();
        $queryCriteria->resetSorting();
        $queryCriteria->resetFacets();
        $queryCriteria->offset(0)->limit(1);
        $properties = $this->getProperties($this->api->filters);
        if (count($properties) == 0) {
            return null;
        }
        $actives = $this->getFilteredValues($criteria);
        return $this->createCollectionResult(
            $facet,
            $properties,
            $actives,
            $criteria
        );
    }
    /**
     * @param QueryBuilder $query
     */
    private function rebuildQuery(QueryBuilder $query)
    {
        $query->resetQueryPart('orderBy');
        $query->resetQueryPart('groupBy');
        $query->innerJoin('product', 's_filter_articles', 'productProperty', 'productProperty.articleID = product.id');
        $query->groupBy('productProperty.valueID');
        $query->select('productProperty.valueID as id');
    }
    /**
     * @param Criteria $criteria
     * @return array
     */
    private function getFilteredValues(Criteria $criteria)
    {
        $values = [];
    		$request = Shopware()->Container()->get('front')->Request();
  	  	$filters = $request->getParam('sFilterProperties', []);
	    	$filters = explode('|', $filters);$ret=array();
	    	foreach($filters as $filter) {
	    		$values[]=$filter;
	    	}
        return $values;
    }
    /**
     * @param Facet\PropertyFacet $facet
     * @param Struct\Property\Set[] $sets
     * @param int[] $actives
     * @return FacetResultGroup
     Originalfassung:
    private function createCollectionResult(
        Facet\PropertyFacet $facet,
        array $sets,
        $actives
    ) {
        $results = [];
        foreach ($sets as $set) {
            foreach ($set->getGroups() as $group) {
                $items = [];
                $useMedia = false;
                $isActive = false;
                foreach ($group->getOptions() as $option) {
                    $listItem = new MediaListItem(
                        $option->getId(),
                        $option->getName(),
                        in_array(
                            $option->getId(),
                            $actives
                        ),
                        $option->getMedia(),
                        $option->getAttributes()
                    );
                    $isActive = ($isActive || $listItem->isActive());
                    $useMedia = ($useMedia || $listItem->getMedia() !== null);
                    $items[] = $listItem;
                }
                if ($useMedia) {
                    $results[] = new MediaListFacetResult(
                        $facet->getName(),
                        $isActive,
                        $group->getName(),
                        $items,
                        $this->fieldName,
                        $group->getAttributes()
                    );
                } else {
                    $results[] = new ValueListFacetResult(
                        $facet->getName(),
                        $isActive,
                        $group->getName(),
                        $items,
                        $this->fieldName,
                        $group->getAttributes()
                    );
                }
            }
        }
        return new FacetResultGroup(
            $results,
            null,
            $facet->getName()
        );
    }
    */
    /**
     * @param Facet\PropertyFacet $facet
     * @param array $sets
     * @param int[] $actives
     * @return FacetResultGroup
     */
    private function createCollectionResult(
        Facet\PropertyFacet $facet,
        array $sets,
        $actives,
        $criteria
    ) {
        $results = [];
          foreach ($sets as $group) {
          	$items = [];
            $useMedia = false;
            $isActive = false;
						$groupId=$group['name'];
						switch ($group['type']) {
							case 'RANGE':
								$results[]=$this->createRangeFacetResult($criteria, $facet, $group);
								break;
							default:
                foreach ($group['values'] as $option) {
                	  $optId=$groupId."_".$option['value'];
                    $listItem = new MediaListItem(
                        $optId,
                        $option['name'],
                        in_array(
                            $optId,
                            $actives
                        ),
                        null,
                        $option
                    );
                    $isActive = ($isActive || $listItem->isActive());
                    $useMedia = ($useMedia || $listItem->getMedia() !== null);
                    $items[] = $listItem;
                }
                if ($useMedia) {
                    $results[] = new MediaListFacetResult(
                        $facet->getName(),
                        $isActive,
                        $group['name'],
                        $items,
                        $this->fieldName,
                        []
                    );
                } else {
                    $results[] = new ValueListFacetResult(
                        $facet->getName(),
                        $isActive,
                        $group['name'],
                        $items,
                        $this->fieldName,
                        []
                    );
                }
            }
          }
        return new FacetResultGroup(
            $results,
            null,
            $facet->getName()
        );
    }    
    /**
     * @param Struct\ShopContextInterface $context
     * @param Criteria $queryCriteria
     * @return Struct\Property\Set[]
     orig::
    protected function getProperties(Struct\ShopContextInterface $context, Criteria $queryCriteria)
    {
        $query = $this->queryBuilderFactory->createQuery($queryCriteria, $context);
        $this->rebuildQuery($query);
        $statement = $query->execute();
        $valueIds = $statement->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($valueIds)) {
            return null;
        }
        $properties = $this->propertyGateway->getList(
            $valueIds,
            $context
        );
        return $properties;
    }
    */
    /**
    	get filters from semknox-API-Structure, filtered by properties
    	@param array $filters - filters-structure from semknox
    */    
    protected function getProperties (array $filters) {
        $excludelist=array('price', 'Preis', 'Preise', 'prices', 'prix', 'Verkaufspreis', 'salesprice');
        $props=array();
    	foreach ($filters as $filter) {
    	    if ( ($filter['type']=='RANGE') && (in_array($filter['name'],$excludelist)) ) {continue;}
    		$props[]=$filter;
    	}
    	return $props;
    }
    public function getPropertyConditionFromRequest() {
    	$request = Shopware()->Container()->get('front')->Request();
    	$filters = $request->getParam('sFilterProperties', []);
    	$filters = explode('|', $filters);$ret=array();
			foreach($filters as $prop) {
				$x1=explode('_',$prop);
				if (count($x1)==2) {
					if (!isset($ret[$x1[0]])) {$ret[$x1[0]]=array();}
					$ret[$x1[0]][]=$x1[1];
				}
			}
    	return $ret;
    }   
    public function getRangePropertyConditionFromRequest($group) {
    	$result=array();$result['min']=$group['min'];$result['max']=$group['max']; $result['found']=false;
    	$request = Shopware()->Container()->get('front')->Request();
    	$key = 'semkMin_'.$group['name'];
    	if (trim($group['unit'])!='') { $key.='_'.$group['unit']; }
    	$h = $request->getParam($key, '');
    	if (trim($h)!='') {
    		$result['min']=$h;
    		$result['found']=true;
    	} else {
    		$result['min']=$group['min'];
    	}	
    	$key = 'semkMax_'.$group['name'];
    	if (trim($group['unit'])!='') { $key.='_'.$group['unit']; }
    	$h = $request->getParam($key,  '');
    	if (trim($h)!='') {
    		$result['max']=$h;
    	  $result['found']=true;
    	} else {
    		$result['max']=$group['max'];
    	}	
    	return $result;
    }   
    /**
     * @param QueryBuilder $query
     * @param ProductAttributeFacet $facet
     * @param Criteria $criteria
     * @return null|RangeFacetResult
     */
    private function createRangeFacetResult($criteria, $facet, $group ) {
/*
        $activeMin = $result['minValues'];
        $activeMax = $result['maxValues'];
        /**@var $condition ProductAttributeCondition*/
/*
        if ($condition = $criteria->getCondition($facet->getName())) {
            $data = $condition->getValue();
            $activeMin = $data['min'];
            $activeMax = $data['max'];
        }
*/				
				$res=$this->getRangePropertyConditionFromRequest($group);
				$vn=$group['name'];
				$idMin='semkMin_'.$group['name'];
				$idMax='semkMax_'.$group['name'];
				if ($group['unitName']) { 
					$vn.=' ('.$group['unit'].')';
				}
        return new RangeFacetResult(
            $facet->getName(),
            $res['found'],
            $group['name'],
            $group['min'],
            $group['max'],
            $res['min'],
            $res['max'],
            $idMin,
            $idMax
        );        
    }    
}
