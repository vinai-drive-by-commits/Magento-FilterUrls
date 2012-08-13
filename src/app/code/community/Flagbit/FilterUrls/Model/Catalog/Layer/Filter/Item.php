<?php
/**
* This file is part of the Flagbit_FilterUrls project.
*
* Flagbit_FilterUrls is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License version 3 as
* published by the Free Software Foundation.
*
* This script is distributed in the hope that it will be useful, but WITHOUT
* ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
* FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
*
* PHP version 5
*
* @category Flagbit_FilterUrls
* @package Flagbit_FilterUrls
* @author Michael Türk <tuerk@flagbit.de>
* @copyright 2012 Flagbit GmbH & Co. KG (http://www.flagbit.de). All rights served.
* @license http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
* @version 0.1.0
* @since 0.1.0
*/
/**
 * Item model for link item of layered navigation.
 *
 * @category Flagbit_FilterUrls
 * @package Flagbit_FilterUrls
 * @author Michael Türk <tuerk@flagbit.de>
 * @copyright 2012 Flagbit GmbH & Co. KG (http://www.flagbit.de). All rights served.
 * @license http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version 0.1.0
 * @since 0.1.0
 */
class Flagbit_FilterUrls_Model_Catalog_Layer_Filter_Item extends Mage_Catalog_Model_Layer_Filter_Item {
    
    /**
    * Get filter item url
    * Overwritten function from the original class to add rewrite to URL.
    *
    * @return string
    */
    public function getUrl()
    {
        $filter = $this->getFilter();
        $category = Mage::registry('current_category');

        $rewrite = Mage::getStoreConfig('web/seo/use_rewrites',Mage::app()->getStore()->getId());
        if($rewrite == 0) {
            return parent::getUrl();
        }

        return $this->getSpeakingFilterUrl(true);
    }
    
    /**
     * Get url for remove item from filter
     * Overwritten function from the original class to add rewrite to URL.
     * 
     * @return string
     */
    public function getRemoveUrl()
    {
        $filter = $this->getFilter();
        $category = Mage::registry('current_category');

        $rewrite = Mage::getStoreConfig('web/seo/use_rewrites', Mage::app()->getStore()->getId());
        if ($rewrite == 0) {
            return parent::getRemoveUrl();
        }


        return $this->getSpeakingFilterUrl(false);
    }
    
    /**
     * Main function for link generation. Implements the following process:
     * (1) get URL path from current category
     * (2) iterate over all state variables
     * (2a) attribute filter: add normalized lowercased option label for each state item ordered by attribute's position
     * (2b) category or price filter: add normal requestVar & value to query
     * (3) potentially add own value (depending on being a getUrl() or getRemoveUrl() call)
     * (4) add seo suffix
     * (5) generate direct link and return
     * 
     * @param boolean $addOwnValue Signals whether or not to add the current item's value to the URL
     * @param boolean $withoutFilter To gain access to the link generation without actually having an attribute model, this switch can be set to TRUE.
     * @param array $additionalQueryParams To pass additional query parameters to the resulting link, this parameter can be used.
     */
    public function getSpeakingFilterUrl($addOwnValue, $withoutFilter = FALSE, $additionalQueryParams = array())
    {
        $category = Mage::registry('current_category');
        
        $filterUrlArray = $this->_getFilterUrlArrayForCurrentState($withoutFilter);
        
        $query = $filterUrlArray['query'];
        $query[Mage::getBlockSingleton('page/html_pager')->getPageVarName()]= null; // exclude current page from urls
        
        if ($addOwnValue) {
            if ($this->getFilter() instanceof Mage_Catalog_Model_Layer_Filter_Attribute) {
                $filterUrlArray['filterUrl'][$this->getFilter()->getAttributeModel()->getPosition()] = $this->_getRewriteForFilterOption($this->getFilter(), $this->getValue());
            }
            else {
                $query[$this->getFilter()->getRequestVar()] = $this->getValue(); 
            }
        }
        
        ksort($filterUrlArray['filterUrl']);
        $filterUrlString = implode('-', $filterUrlArray['filterUrl']);
        
        $url = str_replace(Mage::getStoreConfig('web/unsecure/base_url'), '', $category->getUrl());
        $url = preg_replace('/\?.*/', '', $url);
        
        if (!empty($filterUrlString)) {
            $configUrlSuffix = Mage::getStoreConfig('catalog/seo/category_url_suffix');
            
            if (substr($url, -strlen($configUrlSuffix)) == $configUrlSuffix) {
                $url = substr($url, 0, -strlen($configUrlSuffix));
            }
        
            $url .= '/' . $filterUrlString . $configUrlSuffix;
        }
        
        if (!empty($additionalQueryParams)) {
            $query = array_merge($query, $additionalQueryParams);
        }
        
        $params['_query'] = $query;
        
        return Mage::getModel('core/url')->getDirectUrl($url, array('_query' => $query));;
    }
    
    /**
     * Helper function that gains all information about the current state string. Ignores the current item in the state.
     * 
     * @param boolean $withoutFilter Switches use of current item check off to make processing of links from external possible.
     * @return array Link information for further processing.
     */
    protected function _getFilterUrlArrayForCurrentState($withoutFilter) {
        $filterUrlArray = array();
        $query = array();
        
        foreach (Mage::getSingleton('catalog/layer')->getState()->getFilters() as $item) {
            if (!$withoutFilter && $this->getName() == $item->getName()) {
                continue;
            }
            
            if ($item->getFilter() instanceof Mage_Catalog_Model_Layer_Filter_Attribute) {
                $filterUrlArray[$item->getFilter()->getAttributeModel()->getPosition()] = $this->_getRewriteForFilterOption($item->getFilter(), $item->getValue());
            }
            else {
                $query[$item->getFilter()->getRequestVar()] = $item->getValueString(); 
            }
        }
        
        $filterUrlArray = array('filterUrl' => $filterUrlArray, 'query' => $query);
        
        return $filterUrlArray;
    }
    
    /**
     * Gets rewrite string for given attribute filter - value combination.
     * 
     * @param Mage_Catalog_Model_Layer_Filter_Attribute $filter The given filter attribute model as object.
     * @param int $value The current value to be gathered.
     * @return string Return the gathered string or NULL.
     */
    protected function _getRewriteForFilterOption($filter, $value)
    {
        return Mage::getModel('filterurls/rewrite')
                ->loadByFilterOption($filter, $value)
                ->getRewrite();
    }
}