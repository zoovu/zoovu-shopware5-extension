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
namespace Shopware\Bundle\SearchBundleDBAL\SearchTerm;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Struct\Shop;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
/**
 * @category  Shopware
 * @package   Shopware\Bundle\SearchBundleDBAL\SearchTerm
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
interface SearchTermLoggerInterface
{
    /**
     * Traces the search result into the s_statistic_search
     *
     * @param Criteria $criteria
     * @param ProductNumberSearchResult $result
     * @param Shop $shop
     */
    public function logResult(Criteria $criteria, ProductNumberSearchResult $result, Shop $shop);
}
