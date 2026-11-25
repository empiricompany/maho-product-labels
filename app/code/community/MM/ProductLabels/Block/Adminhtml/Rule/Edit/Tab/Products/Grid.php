<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

/**
 * Read-only grid of the products labelled by the rule.
 *
 * The rows come from the label index, so the grid shows what the storefront shows
 * right now. It is a child of the tab block: the tabs block renames the id of its
 * own child, so the grid must keep its id to match its JavaScript object.
 */
class MM_ProductLabels_Block_Adminhtml_Rule_Edit_Tab_Products_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('productlabelsRuleProductsGrid');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(false);
    }

    public function getRule(): MM_ProductLabels_Model_Rule
    {
        $rule = Mage::registry('productlabels_rule');
        if ($rule instanceof MM_ProductLabels_Model_Rule) {
            return $rule;
        }

        $rule = Mage::getModel('productlabels/rule');
        $ruleId = (int) $this->getRequest()->getParam('rule_id');
        if ($ruleId > 0) {
            $rule->load($ruleId);
        }
        return $rule;
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        $collection = Mage::getResourceModel('catalog/product_collection');
        if (!$collection instanceof Mage_Catalog_Model_Resource_Product_Collection) {
            throw new Mage_Core_Exception('Product collection not available.');
        }
        $collection->addAttributeToSelect(['name', 'sku', 'price', 'status', 'visibility', 'type_id', 'attribute_set_id']);
        if ($this->isModuleEnabled('Mage_CatalogInventory', 'catalog')) {
            $collection->joinField(
                'qty',
                'cataloginventory/stock_item',
                'qty',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left',
            );
        }

        $indexSelect = Mage::helper('productlabels')->getConnection()->select()
            ->from(
                (string) Mage::getSingleton('core/resource')->getTableName('productlabels/index'),
                ['product_id'],
            )
            ->where('rule_id = ?', (int) $this->getRule()->getId());

        // The index holds one row per website and customer group, the grid one row per product.
        $collection->getSelect()->where('e.entity_id IN (' . $indexSelect . ')');

        $this->setCollection($collection);
        $result = parent::_prepareCollection();
        $collection->addWebsiteNamesToResult();
        return $result;
    }

    /**
     * The websites column reads the names from the loaded rows, so the filter needs the
     * product-website table in the query: same trick as Catalog > Manage Products.
     */
    #[\Override]
    protected function _addColumnFilterToCollection($column): static
    {
        $collection = $this->getCollection();
        $filter = $column->getFilter();
        if ($column->getId() === 'websites'
            && $collection instanceof Mage_Catalog_Model_Resource_Product_Collection
            && $filter instanceof Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract
            && $filter->getValue()
        ) {
            $collection->joinField(
                'websites',
                'catalog/product_website',
                'website_id',
                'product_id=entity_id',
                null,
                'left',
            );
        }
        return parent::_addColumnFilterToCollection($column);
    }

    #[\Override]
    protected function _prepareColumns(): static
    {
        $helper = Mage::helper('productlabels');

        $this->addColumn('entity_id', [
            'header' => $helper->__('ID'),
            'index' => 'entity_id',
            'width' => '60px',
            'align' => 'right',
        ]);
        $this->addColumn('name', [
            'header' => $helper->__('Name'),
            'index' => 'name',
        ]);
        $this->addColumn('type', [
            'header' => $helper->__('Type'),
            'width' => '150px',
            'index' => 'type_id',
            'type' => 'options',
            'options' => Mage::getSingleton('catalog/product_type')->getOptionArray(),
        ]);
        $this->addColumn('set_name', [
            'header' => $helper->__('Attrib. Set Name'),
            'width' => '150px',
            'index' => 'attribute_set_id',
            'type' => 'options',
            'options' => $this->_getAttributeSetOptions(),
        ]);
        $this->addColumn('sku', [
            'header' => $helper->__('SKU'),
            'index' => 'sku',
            'width' => '140px',
        ]);
        $this->addColumn('price', [
            'header' => $helper->__('Price'),
            'index' => 'price',
            'type' => 'price',
            'width' => '90px',
            'currency_code' => (string) Mage::app()->getBaseCurrencyCode(),
        ]);
        if ($this->isModuleEnabled('Mage_CatalogInventory', 'catalog')) {
            $this->addColumn('qty', [
                'header' => $helper->__('Qty'),
                'width' => '100px',
                'type' => 'number',
                'index' => 'qty',
            ]);
        }
        $this->addColumn('visibility', [
            'header' => $helper->__('Visibility'),
            'width' => '60px',
            'align' => 'center',
            'index' => 'visibility',
            'type' => 'options',
            'options' => Mage::getModel('catalog/product_visibility')->getOptionArray(),
            // Same icons as Catalog > Manage Products.
            'renderer' => 'adminhtml/widget_grid_column_renderer_iconoptions',
            'filter' => 'adminhtml/widget_grid_column_filter_iconoptions',
            'icons' => [
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE => 'eye-off',
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG => 'layout-grid',
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH => 'search',
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH => ['layout-grid', 'search'],
            ],
        ]);
        $this->addColumn('status', [
            'header' => $helper->__('Status'),
            'width' => '60px',
            'align' => 'center',
            'index' => 'status',
            'type' => 'options',
            'options' => Mage::getSingleton('catalog/product_status')->getOptionArray(),
            'renderer' => 'adminhtml/widget_grid_column_renderer_iconoptions',
            'filter' => 'adminhtml/widget_grid_column_filter_iconoptions',
            'icons' => [
                Mage_Catalog_Model_Product_Status::STATUS_ENABLED => ['icon' => 'circle', 'variant' => 'filled', 'class' => 'grid-icon-value--on'],
                Mage_Catalog_Model_Product_Status::STATUS_DISABLED => ['icon' => 'circle', 'variant' => 'filled', 'class' => 'grid-icon-value--off'],
            ],
        ]);
        $this->addColumn('websites', [
            'header' => $helper->__('Websites'),
            'index' => 'websites',
            'type' => 'options',
            'width' => '120px',
            'sortable' => false,
            'options' => Mage::getModel('core/website')->getCollection()->toOptionHash(),
        ]);

        return parent::_prepareColumns();
    }

    /**
     * @return array<int, string>
     */
    private function _getAttributeSetOptions(): array
    {
        return Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter(Mage::getModel('catalog/product')->getResource()->getTypeId())
            ->setOrder('attribute_set_name', 'asc')
            ->load()
            ->toOptionHash();
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/productsGrid', ['_current' => true]);
    }

    /**
     * Read-only grid: '#' is how the core grid template marks a row with no link.
     */
    #[\Override]
    public function getRowUrl($row): string
    {
        return '#';
    }
}
