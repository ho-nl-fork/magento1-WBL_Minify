<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Page
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Html page block
 *
 * @category   Mage
 * @package    Mage_Page
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class WBL_Minify_Block_Page_Html_Head extends WBL_Minify_Block_Page_Html_Head_Abstract
{
    /*Mirasvit Head Class*/
    protected function _construct()
    {
        parent::_construct();
        $this->setupCanonicalUrl();
        $this->setupAlternateTag();
    }

    public function getConfig()
    {
        return Mage::getSingleton('seo/config');
    }

    public function getRobots()
    {
        if (!$this->getAction()) {
            return;
        }
        if ($product = Mage::registry('current_product')) {
            if ($robots = Mage::helper('seo')->getMetaRobotsByCode($product->getSeoMetaRobots())) {
                return $robots;
            }
        }
        $fullAction = $this->getAction()->getFullActionName();
        foreach ($this->getConfig()->getNoindexPages() as $record) {
            //for patterns like filterattribute_(arttribte_code) and filterattribute_(Nlevel)
            if (strpos($record['pattern'], 'filterattribute_(') !== false
                && $fullAction == 'catalog_category_view') {
                if ($this->_checkFilterPattern($record['pattern'])) {
                    return Mage::helper('seo')->getMetaRobotsByCode($record->getOption());
                }
            }

            if (Mage::helper('seo')->checkPattern($fullAction, $record->getPattern())
                || Mage::helper('seo')->checkPattern(Mage::helper('seo')->getBaseUri(), $record['pattern'])) {
                return Mage::helper('seo')->getMetaRobotsByCode($record->getOption());
            }
        }

        return parent::getRobots();
    }

    protected function _checkFilterPattern($pattern)
    {
        $urlParams = Mage::app()->getFrontController()->getRequest()->getQuery();
        if (!Mage::getSingleton('catalog/layer')->getFilterableAttributes()) {
            return false;
        }
        $currentFilters = Mage::getSingleton('catalog/layer')->getFilterableAttributes()->getData();
        $filterArr = array();
        foreach ($currentFilters as $filterAttr) {
            if (isset($filterAttr['attribute_code'])) {
                $filterArr[] = $filterAttr['attribute_code'];
            }
        }

        $usedFilters = array();
        if (!empty($filterArr)) {
            foreach ($urlParams as $keyParam => $valParam) {
                if (in_array($keyParam, $filterArr)) {
                    $usedFilters[] = $keyParam;
                }
            }
        }

        if (!empty($usedFilters)) {
            $usedFiltersCount = count($usedFilters);
            if (strpos($pattern, 'level)') !== false) {
                preg_match('/filterattribute_\\((\d{1})level/', trim($pattern), $levelNumber);
                if (isset($levelNumber[1])) {
                    if ($levelNumber[1] == $usedFiltersCount) {
                        return true;
                    }
                }
            }

            foreach($usedFilters as $useFilterVal) {
                if (strpos($pattern, '(' . $useFilterVal . ')') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function setupCanonicalUrl()
    {
        if (!$this->getConfig()->isAddCanonicalUrl()) {
            return;
        }

        if (!$this->getAction()) {
            return;
        }

        if (Mage::app()->getRequest()->getModuleName() == 'amlanding') {
            return;
        }

        $fullAction = $this->getAction()->getFullActionName();
        foreach ($this->getConfig()->getCanonicalUrlIgnorePages() as $page) {
            if (Mage::helper('seo')->checkPattern($fullAction, $page)
                || Mage::helper('seo')->checkPattern(Mage::helper('seo')->getBaseUri(), $page)) {
                return;
            }
        }

        $productActions = array(
            'catalog_product_view',
            'review_product_list',
            'review_product_view',
            'productquestions_show_index',
        );

        $productCanonicalStoreId = false;
        $useCrossDomain = true;
        if (in_array($fullAction, $productActions)) {
            $product = Mage::registry('current_product');
            if (!$product) {
                return;
            }
            $productCanonicalStoreId = $product->getSeoCanonicalStoreId(); //canonical store id for current product
            $canonicalUrlForCurrentProduct = trim($product->getSeoCanonicalUrl());

            $collection = Mage::getModel('catalog/product')->getCollection()
                ->addFieldToFilter('entity_id', $product->getId())
                ->addStoreFilter()
                ->addUrlRewrite();

            $product      = $collection->getFirstItem();
            $canonicalUrl = $product->getProductUrl();

            if ($canonicalUrlForCurrentProduct) {
                if (strpos($canonicalUrlForCurrentProduct, 'http://') !== false
                    || strpos($canonicalUrlForCurrentProduct, 'https://') !== false) {
                    $canonicalUrl = $canonicalUrlForCurrentProduct;
                    $useCrossDomain = false;
                } else {
                    $canonicalUrlForCurrentProduct = (substr($canonicalUrlForCurrentProduct, 0, 1) == '/') ? substr($canonicalUrlForCurrentProduct, 1) : $canonicalUrlForCurrentProduct;
                    $canonicalUrl = Mage::getBaseUrl() . $canonicalUrlForCurrentProduct;
                }
            }
        } elseif ($fullAction == 'catalog_category_view') {
            $category     = Mage::registry('current_category');
            if (!$category) {
                return;
            }
            $canonicalUrl = $category->getUrl();
        } else {
            $canonicalUrl = Mage::helper('seo')->getBaseUri();
            $canonicalUrl = Mage::getUrl('', array('_direct' => ltrim($canonicalUrl, '/')));
            $canonicalUrl = strtok($canonicalUrl, '?');
        }

        //setup crossdomian URL if this option is enabled
        if ((($crossDomainStore = $this->getConfig()->getCrossDomainStore()) || $productCanonicalStoreId) && $useCrossDomain) {
            if ($productCanonicalStoreId) {
                $crossDomainStore = $productCanonicalStoreId;
            }
            $mainBaseUrl = Mage::app()->getStore($crossDomainStore)->getBaseUrl();
            $currentBaseUrl = Mage::app()->getStore()->getBaseUrl();
            $canonicalUrl = str_replace($currentBaseUrl, $mainBaseUrl, $canonicalUrl);

            if (Mage::app()->getStore()->isCurrentlySecure()) {
                $canonicalUrl = str_replace('http://', 'https://', $canonicalUrl);
            }
        }

        if (false && isset($product)) { //возможно в перспективе вывести это в конфигурацию. т.к. это нужно только в некоторых случаях.
            // если среди категорий продукта есть корневая категория, то устанавливаем ее для каноникал
            $categoryIds = $product->getCategoryIds();

            if (Mage::helper('catalog/category_flat')->isEnabled()) {
                $currentStore = Mage::app()->getStore()->getId();
                foreach (Mage::app()->getStores() as $store) {
                    Mage::app()->setCurrentStore($store->getId());
                    $collection = Mage::getModel('catalog/category')->getCollection()
                        ->addFieldToFilter('entity_id', $categoryIds)
                        ->addFieldToFilter('level', 1);
                    if ($collection->count()) {
                        $mainBaseUrl = $store->getBaseUrl();
                        break;
                    }
                }
                Mage::app()->setCurrentStore($currentStore);
                if (isset($mainBaseUrl)) {
                    $currentBaseUrl = Mage::app()->getStore()->getBaseUrl();
                    $canonicalUrl = str_replace($currentBaseUrl, $mainBaseUrl, $canonicalUrl);
                }
            } else {
                $collection = Mage::getModel('catalog/category')->getCollection()
                    ->addFieldToFilter('entity_id', $categoryIds)
                    ->addFieldToFilter('level', 1);
                if ($collection->count()) {
                    $rootCategory = $collection->getFirstItem();
                    foreach (Mage::app()->getStores() as $store) {
                        if ($store->getRootCategoryId() == $rootCategory->getId()) {
                            $mainBaseUrl = $store->getBaseUrl();
                            $currentBaseUrl = Mage::app()->getStore()->getBaseUrl();
                            $canonicalUrl = str_replace($currentBaseUrl, $mainBaseUrl, $canonicalUrl);
                        }
                    }
                }
            }
        }

        
        $page = (int)Mage::app()->getRequest()->getParam('p');
        // if ($page > 1) {
        //     $canonicalUrl .= "?p=$page";
        // }
        if ($page == 2) {
            $canonicalUrl .= " ";
        }

        $this->addLinkRel('canonical', $canonicalUrl);
    }

    public function setupAlternateTag()
    {
        if (!$this->getConfig()->isAlternateHreflangEnabled(Mage::app()->getStore()->getStoreId()) || !$this->getAction()) {
            return;
        }

        $isMagentoEe = false;
        if (Mage::helper('mstcore/version')->getEdition() == 'ee') {
            $isMagentoEe = true;
        }

        $fullAction = $this->getAction()->getFullActionName();
        $currentStoreGroup = Mage::app()->getStore()->getGroupId();
        if (Mage::app()->getRequest()->getControllerName() == 'product'
            || Mage::app()->getRequest()->getControllerName() == 'category'
            || Mage::app()->getRequest()->getModuleName() == 'cms'
            || Mage::app()->getRequest()->getModuleName() == 'amlanding'
        ) {
            $storesNumberInGroup = 0;
            $storesArray = array();
            $storesBaseUrls = array();
            $xDefaultUrl = '';

            foreach (Mage::app()->getStores() as $store) {
                if ($store->getIsActive() && $store->getGroupId() == $currentStoreGroup) { //we works only with stores which have the same store group
                    $storesArray[$store->getId()] = $store;
                    $storesBaseUrls[$store->getId()] = $store->getBaseUrl();
                    $storesNumberInGroup++;
                }
            }
            $storesBaseUrlsCountValues = array_count_values($storesBaseUrls); //array with quantity of identical Base Urls

            if ($storesNumberInGroup > 1) { //if a current store is multilanguage
                $isAlternateAdded = false;
                if (($cmsPageId = Mage::getSingleton('cms/page')->getPageId())
                    && Mage::app()->getRequest()->getActionName() != 'noRoute'
                ) {
                    $cmsStoresIds = Mage::getSingleton('cms/page')->getStoreId();
                    $cmsCollection = Mage::getModel('cms/page')->getCollection()
                        ->addFieldToSelect('alternate_group')
                        ->addFieldToFilter('page_id', array('eq' => $cmsPageId))
                        ->getFirstItem();
                    if (($alternateGroup = $cmsCollection->getAlternateGroup()) && $cmsStoresIds[0] != 0) {
                        $cmsCollection = Mage::getModel('cms/page')->getCollection()
                            ->addFieldToSelect(array('alternate_group', 'identifier'))
                            ->addFieldToFilter('alternate_group', array('eq' => $alternateGroup))
                            ->addFieldToFilter('is_active', true);
                        $table = Mage::getSingleton('core/resource')->getTableName('cms/page_store');
                        $cmsCollection->getSelect()
                            ->join(array('storeTable' => $table), 'main_table.page_id = storeTable.page_id', array('store_id' => 'storeTable.store_id'));
                        $cmsHierarchyCollection = clone $cmsCollection;
                        $cmsPages = $cmsCollection->getData();
                        if ($isMagentoEe) {
                            $cmsHierarchyCollection->clear();
                            $table = Mage::getSingleton('core/resource')->getTableName('enterprise_cms/hierarchy_node');
                            $cmsHierarchyCollection->getSelect()->join(array('cmsHierarchyTable' => $table), 'main_table.page_id = cmsHierarchyTable.page_id', array('hierarchy_request_url' => 'request_url'));
                            if ($cmsHierarchyPages = $cmsHierarchyCollection->getData()) {
                                $cmsPages = array_merge_recursive($cmsHierarchyPages, $cmsPages);
                                $storeArray = array();
                                foreach ($cmsPages as $keyCmsPages => $valueCmsPages) {
                                    if (in_array($valueCmsPages['store_id'], $storeArray)) {
                                        unset($cmsPages[$keyCmsPages]);
                                    }
                                    $storeArray[] = $valueCmsPages['store_id'];
                                }
                            }
                        }
                        if (count($cmsPages) > 0) {
                            $alternateLinks = array();
                            foreach ($cmsPages as $page) {
                                $pageIdentifier = ($isMagentoEe && isset($page['hierarchy_request_url'])) ? $page['hierarchy_request_url'] : $page['identifier'];
                                $url = ($fullAction == 'cms_index_index') ? Mage::app()->getStore($page['store_id'])->getBaseUrl() : Mage::app()->getStore($page['store_id'])->getBaseUrl() . $pageIdentifier;
                                $alternateLinks[$page['store_id']] = $url;
                            }
                            if (count($alternateLinks) > 0) {
                                foreach ($alternateLinks as $storeId => $storeUrl) {
                                    //need if we have the same product url for every store, will add something like ?___store=frenchurl
                                    $urlAddition = (isset($storesBaseUrlsCountValues[$storesArray[$storeId]->getBaseUrl()]) && $storesBaseUrlsCountValues[$storesArray[$storeId]->getBaseUrl()] > 1) ? strstr(htmlspecialchars_decode($storesArray[$storeId]->getCurrentUrl(false)), "?") : '';
                                    $urlAddition = $this->getPreparedUrlAdditionalForCms($urlAddition);
                                    $storeCodeCms = substr(Mage::getStoreConfig('general/locale/code', $storeId), 0, 2);
                                    if ($urlAddition && !$xDefaultUrl) { //x-default alternate
                                        $xDefaultUrl = $storeUrl;
                                    }
                                    if ($localeCodeCms = $this->getConfig()->getHreflangLocaleCode($storeId)) { //hreflang locale code
                                        $storeCodeCms .= "-" . $localeCodeCms;
                                    }
                                    $this->addLinkRel('alternate"' . ' hreflang="' . $storeCodeCms, $storeUrl . $urlAddition . " ");
                                }

                                $isAlternateAdded = true;

                            }
                        }
                    }
                }

                if (!$isAlternateAdded) {
                    $currentStore = Mage::app()->getStore()->getId();
                    /** @var Mage_Core_Model_Store $store */
                    foreach ($storesArray as $store) {
                        $storeCode = substr(Mage::getStoreConfig('general/locale/code', $store->getId()), 0, 2);
                        $addLinkRel = false;
                        //need if we have the same product url for every store, will add something like ?___store=frenchurl
                        $urlAddition = (isset($storesBaseUrlsCountValues[$store->getBaseUrl()]) && $storesBaseUrlsCountValues[$store->getBaseUrl()] > 1) ? strstr(htmlspecialchars_decode($store->getCurrentUrl(false)), "?") : '';
                        if (Mage::app()->getRequest()->getModuleName() == 'cms'
                            && Mage::app()->getRequest()->getActionName() != 'noRoute'
                        ) {
                            $urlAdditionCms = $this->getPreparedUrlAdditionalForCms($urlAddition);
                            if ($isMagentoEe
                                && ($currentNode = Mage::registry('current_cms_hierarchy_node'))
                                && ($cmsHierarchyRequestUrl = $currentNode->getRequestUrl())
                            ) {
                                $url = ($fullAction == 'cms_index_index') ? $store->getBaseUrl() . $urlAdditionCms : $store->getBaseUrl() . $cmsHierarchyRequestUrl . $urlAdditionCms;
                            } else {
                                $url = ($fullAction == 'cms_index_index') ? $store->getBaseUrl() . $urlAdditionCms : $store->getBaseUrl() . Mage::getSingleton('cms/page')->getIdentifier() . $urlAdditionCms;
                            }
                            $addLinkRel = true;
                        }
                        if (Mage::app()->getRequest()->getModuleName() == 'amlanding') {
                            $url = $store->getBaseUrl() . ltrim(Mage::app()->getRequest()->getParam('am_landing'), '/') . '/';
                            $addLinkRel = true;
                        }
                        if (Mage::app()->getRequest()->getControllerName() == 'product') {
                            $product = Mage::registry('current_product');
                            if (!$product) {
                                return;
                            }
                            $category = Mage::registry('current_category');
                            $category ? $categoryId = $category->getId() : $categoryId = null;
                            if ($isMagentoEe) {
                                $url = $store->getBaseUrl() . $this->getEeAlternateProductUrl() . $urlAddition;
                            } else {
                                $url = $store->getBaseUrl() . $this->getAlternateProductUrl($product->getId(), $categoryId, $store->getId()) . $urlAddition;
                            }
                            $addLinkRel = true;
                        }
                        if (Mage::app()->getRequest()->getControllerName() == 'category') {
                            $currentStoreUrl = $store->getCurrentUrl(false);
                            $currentUrl = Mage::helper('core/url')->getCurrentUrl();
                            $category = Mage::getModel('catalog/category')->getCollection()
                                ->setStoreId($store->getId())
                                ->addFieldToFilter('is_active', array('eq' => '1'))
                                ->addFieldToFilter('entity_id', array('eq' => Mage::registry('current_category')->getId()))
                                ->getFirstItem();

                            if ($category->hasData() && ($currentCategory = Mage::getModel('catalog/category')->setStoreId($store->getId())->load($category->getEntityId()))) {
                                $categoryUrl = $store->getBaseUrl() . $currentCategory->getUrlPath() . $urlAddition;
                                $categoryUrlPath = $currentCategory->getUrlPath();
                                $requestString = Mage::getSingleton('core/url')->escape(ltrim(Mage::app()->getRequest()->getRequestString(), '/'));
                                if ($suffix = Mage::helper('catalog/category')->getCategoryUrlSuffix($store->getId())) {
                                    $currentStoreSuffix = Mage::helper('catalog/category')->getCategoryUrlSuffix(Mage::app()->getStore()->getStoreId());
                                    //add correct suffix for every store
                                    $requestString = preg_replace('~' . $currentStoreSuffix . '$~ims', $suffix, $requestString);
                                    $categoryUrlPath = preg_replace('~' . $suffix . '$~ims', '', $categoryUrlPath);
                                }

                                if (strpos($requestString, $categoryUrlPath) === false) { //create correct category way for every store, need if category use different path
                                    $slashCountCategoryUrlPath = substr_count($categoryUrlPath, '/');
                                    $slashCountRequestString = substr_count($requestString, '/');
                                    $requestStringParts = explode('/', $requestString);
                                    $requestStringCategoryPart = implode('/', array_slice($requestStringParts, 0, $slashCountCategoryUrlPath + 1));
                                    if ($slashCountCategoryUrlPath == $slashCountRequestString && $suffix) {
                                        $requestString = str_replace($requestStringCategoryPart, $categoryUrlPath, $requestString) . $suffix;
                                    } else {
                                        $requestString = str_replace($requestStringCategoryPart, $categoryUrlPath, $requestString);
                                    }
                                }
                                $preparedUrlAdditionCurrent = $this->getUrlAdditionalParsed(strstr($currentUrl, "?"));
                                $preparedUrlAdditionStore = $this->getUrlAdditionalParsed($urlAddition);
                                $urlAdditionCategory = $this->getPreparedUrlAdditional($preparedUrlAdditionCurrent, $preparedUrlAdditionStore);

                                if ($this->_useAlgoritmForDifferentAttributes) { // need if store use different attributes name
                                    $requestString = $this->getFilterPageRequestString($store->getId(), $requestString, $categoryUrlPath, $storesArray);
                                }
                                //$url = $store->getBaseUrl() . $requestString . $urlAdditionCategory;
                                $url = $store->getBaseUrl() . $categoryUrlPath . "/";
                            }

                            $addLinkRel = true;
                        }

                        if ($addLinkRel && isset($url)) { //need to don't break store if $url not exist
                            if ($urlAddition && !$xDefaultUrl) { //x-default alternate
                                $xDefaultUrl = $url;
                            }
                            if ($localeCode = $this->getConfig()->getHreflangLocaleCode($store->getId())) { //hreflang locale code
                                $storeCode .= "-" . $localeCode;
                            }
                            // echo "storeCode: ".$storeCode ." ||| alternate url: ". $url . "<br/>";
                            $this->addLinkRel('alternate"' . ' hreflang="' . $storeCode, $url . " ");
                            $isAlternateAdded = true;
                        }
                    }
                }

                //x-default alternate
                if ($isAlternateAdded && $xDefaultUrl) {
                    $xDefaultUrl = $this->getPreparedXDefaultUrl($xDefaultUrl);
                    // echo "storeCode: x-default ||| alternate url: ". $url . "<br/>";
                    $this->addLinkRel('alternate"' . ' hreflang="x-default', $xDefaultUrl . " ");
                }
            }

        }
    }

    public function getAlternateProductUrl($productId, $categoryId, $storeId)
    {
        $idPath = sprintf('product/%d', $productId);
        if ($categoryId && $this->getConfig()->getProductUrlFormat() != Mirasvit_Seo_Model_Config::URL_FORMAT_SHORT) {
            $idPath = sprintf('%s/%d', $idPath, $categoryId);
        }
        $urlRewriteObject = Mage::getModel('core/url_rewrite')->setStoreId($storeId)->loadByIdPath($idPath);

        return $urlRewriteObject->getRequestPath();
    }

    /*Mirasvit Head Class*/

    /**
     * Add CSS file to HEAD entity
     *
     * @param string $name
     * @param string $params
     * @param string $group
     * @return WBL_Minify_Block_Page_Html_Head
     */
    public function addCss($name, $params = "", $group='default')
    {
        $this->addItem('skin_css', $name, $params, null, null, $group);
        return $this;
    }


    /**
     * Add JavaScript file to HEAD entity
     *
     * @param string $name
     * @param string $params
     * @param string $group
     * @return WBL_Minify_Block_Page_Html_Head
     */
    public function addJs($name, $params = "", $group='default')
    {
        $this->addItem('js', $name, $params, null, null, $group);
        return $this;
    }


    /**
     * Add CSS file for Internet Explorer only to HEAD entity
     *
     * @param string $name
     * @param string $params
     * @param string $group
     * @return WBL_Minify_Block_Page_Html_Head
     */
    public function addCssIe($name, $params = "", $group='default')
    {
        $this->addItem('skin_css', $name, $params, 'IE', null, $group);
        return $this;
    }


    /**
     * Add JavaScript file for Internet Explorer only to HEAD entity
     *
     * @param string $name
     * @param string $params
     * @param string $group
     * @return WBL_Minify_Block_Page_Html_Head
     */
    public function addJsIe($name, $params = "", $group='default')
    {
        $this->addItem('js', $name, $params, 'IE', null, $group);
        return $this;
    }


    /**
     * Add Link element to HEAD entity
     *
     * @param string $rel  forward link types
     * @param string $href URI for linked resource
     * @param string $group
     * @return Mage_Page_Block_Html_Head
     */
    public function addLinkRel($rel, $href, $group='default')
    {
        $this->addItem('link_rel', $href, 'rel="' . $rel . '"', null, null, $group);
        return $this;
    }


    /**
     * Add HEAD Item
     *
     * Allowed types:
     *  - js
     *  - js_css
     *  - skin_js
     *  - skin_css
     *  - rss
     *
     * @param string $type
     * @param string $name
     * @param string $params
     * @param string $if
     * @param string $cond
     * @param string $group
     * @return WBL_Minify_Block_Page_Html_Head
     */
    public function addItem($type, $name, $params=null, $if=null, $cond=null, $group='default')
    {
        if (($type==='skin_css' || $type==='skin_less') && empty($params)) {
            $params = 'media="all"';
        }
        $this->_data['items'][$type.'/'.$name] = array(
            'type'   => $type,
            'name'   => $name,
            'params' => $params,
            'if'     => (string) $if,
            'cond'   => (string) $cond,
            'group'  => (string) $group
        );
        return $this;
    }

    /**
     * Remove Item from HEAD entity
     *
     * @param string $type
     * @param string $name
     * @return WBL_Minify_Block_Page_Html_Head
     */
    public function removeItem($type, $name)
    {
        unset($this->_data['items'][$type.'/'.$name]);
        return $this;
    }

    /**
     * Classify HTML head item and queue it into "lines" array
     *
     * @see self::getCssJsHtml()
     * @param array &$lines
     * @param string $itemIf
     * @param string $itemType
     * @param string $itemParams
     * @param string $itemName
     * @param array $itemThe
     */
    protected function _separateOtherHtmlHeadElements(&$lines, $itemIf, $itemType, $itemParams, $itemName, $itemThe)
    {
    	$params = $itemParams ? ' ' . $itemParams : '';
    	$href   = $itemName;
        $group  = isset($itemThe['group']) ? $itemThe['group'] : 'default';
    	switch ($itemType) {
    		case 'rss':
    			$lines[$group][$itemIf]['other'][] = sprintf('<link href="%s"%s rel="alternate" type="application/rss+xml" />',
    			$href, $params
    			);
    			break;
    		case 'link_rel':
    			$lines[$group][$itemIf]['other'][] = sprintf('<link%s href="%s" />', $params, $href);
    			break;
    	}
    }

    /**
     * Get HEAD HTML with CSS/JS/RSS definitions
     * (actually it also renders other elements, TODO: fix it up or rename this method)
     *
     * @return string
     */
    public function getCssJsHtml()
    {
        // separate items by types
        $lines  = array();
        foreach ($this->_data['items'] as $item) {
            if (!is_null($item['cond']) && !$this->getData($item['cond']) || !isset($item['name'])) {
                continue;
            }
            $if     = !empty($item['if']) ? $item['if'] : '';
            $params = !empty($item['params']) ? $item['params'] : '';

            switch ($item['type']) {
                case 'js':        // js/*.js
                case 'skin_js':   // skin/*/*.js
                case 'js_css':    // js/*.css
                case 'skin_css':  // skin/*/*.css
                case 'js_less':   // js/*.less
                case 'skin_less': // skin/*/*.less
                    $lines[$item['group']][$if][$item['type']][$params][$item['name']] = $item['name'];
                    break;
                default:
                    $this->_separateOtherHtmlHeadElements($lines, $if, $item['type'], $params, $item['name'], $item);
                    break;
            }
        }

        //move less_js always to the end.
        if (isset($lines['less_js'])){
            $lessJs = $lines['less_js'];
            unset($lines['less_js']);
            $lines['less_js'] = $lessJs;
        }

        // prepare HTML
        $shouldMergeJs = Mage::getStoreConfigFlag('dev/js/merge_files');
        $shouldMergeCss = Mage::getStoreConfigFlag('dev/css/merge_css_files');
        $html   = '';
        foreach ($lines as $group => $ifs) {
            $html .= "<!--group: $group-->\n";
            foreach ($ifs as $if => $items) {
                if (empty($items)) {
                    continue;
                }
                if (!empty($if)) {
                    $html .= '<!--[if '.$if.']>'."\n";
                }
                // static and skin css
                $html .= $this->_prepareStaticAndSkinElements('<link rel="stylesheet" type="text/css" href="%s"%s />' . "\n",
                    empty($items['js_css']) ? array() : $items['js_css'],
                    empty($items['skin_css']) ? array() : $items['skin_css'],
                    $shouldMergeCss ? array(Mage::getDesign(), 'getMergedCssUrl') : null
                );

                // static and skin css
                $type = $shouldMergeCss ? 'text/css' : 'text/less';
                $html .= $this->_prepareStaticAndSkinElements('<link rel="stylesheet" type="'.$type.'" href="%s"%s />' . "\n",
                    empty($items['js_less']) ? array() : $items['js_less'],
                    empty($items['skin_less']) ? array() : $items['skin_less'],
                    $shouldMergeCss ? array(Mage::getDesign(), 'getMergedCssUrl') : null
                );

                // static and skin javascripts
                $html .= $this->_prepareStaticAndSkinElements('<script type="text/javascript" src="%s"%s></script>' . "\n",
                    empty($items['js']) ? array() : $items['js'],
                    empty($items['skin_js']) ? array() : $items['skin_js'],
                    $shouldMergeJs ? array(Mage::getDesign(), 'getMergedJsUrl') : null
                );

                // other stuff
                if (!empty($items['other'])) {
                    $html .= $this->_prepareOtherHtmlHeadElements($items['other']) . "\n";
                }

                if (!empty($if)) {
                    $html .= '<![endif]-->'."\n";
                }
            }
        }
        return $html;
    }
}
