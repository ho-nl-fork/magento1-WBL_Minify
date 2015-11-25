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
        if ($page > 1) {
            $canonicalUrl .= "?p=$page";
        }

        $this->addLinkRel('canonical', $canonicalUrl);
    }

    public function setupAlternateTag()
    {
        if (!$this->getConfig()->isAlternateHreflangEnabled(Mage::app()->getStore()->getStoreId())) {
            return;
        }

        $currentStoreGroup = Mage::app()->getStore()->getGroupId();
        if (Mage::app()->getRequest()->getControllerName() == 'product'
            || Mage::app()->getRequest()->getControllerName() == 'category'
            || Mage::app()->getRequest()->getModuleName() == 'cms') {
            $storesNumberInGroup = 0;
            $storesArray = array();
            foreach (Mage::app()->getStores() as $store)
            {
                if ($store->getIsActive() && $store->getGroupId() == $currentStoreGroup) {
                    $storesArray[] = $store;
                    $storesNumberInGroup++;
                }
            }

            if ($storesNumberInGroup > 1 ) { //if a current store is multilanguage
                foreach ($storesArray as $store)
                {
                    $url =  htmlspecialchars_decode($store->getCurrentUrl(false));
                    $storeCode = substr(Mage::getStoreConfig('general/locale/code', $store->getId()),0,2);
                    $addLinkRel = false;
                    if (Mage::app()->getRequest()->getModuleName() == 'cms'
                        && Mage::app()->getRequest()->getActionName() != 'noRoute') {
                        $cmsStoresIds = Mage::getSingleton('cms/page')->getStoreId();
                        if (in_array($store->getId(), Mage::getSingleton('cms/page')->getStoreId())
                            || (isset($cmsStoresIds[0]) && $cmsStoresIds[0] == 0)) {
                            $addLinkRel = true;
                        }
                    }
                    if (Mage::app()->getRequest()->getControllerName() == 'product') {
                        $urlAddition = strstr($url,"?"); //need if we have the same product url for every store, will add something like ?___store=frenchurl
                        $product = Mage::registry('current_product');
                        if (!$product) {
                            return;
                        }
                        $category = Mage::registry('current_category');
                        $category ? $categoryId = $category->getId() : $categoryId = null;
                        $url = $store->getBaseUrl() . $this->getAlternateProductUrl($product->getId(), $categoryId, $store->getId()) . $urlAddition;
                        $addLinkRel = true;
                    }
                    if (Mage::app()->getRequest()->getControllerName() == 'category') {
                        $collection = Mage::getModel('catalog/category')->getCollection()
                            ->setStoreId($store->getId())
                            ->addFieldToFilter('is_active', array('eq'=>'1'))
                            ->addFieldToFilter('entity_id', array('eq'=>Mage::registry('current_category')->getId()))
                            ->getFirstItem();
                        if($collection->hasData()) {
                            $addLinkRel = true;
                        }
                    }
                    if ($addLinkRel) {
                        $this->addLinkRel('alternate"' . ' hreflang="' . $storeCode, $url);
                    }
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
