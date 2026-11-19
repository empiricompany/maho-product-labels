<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

final class MM_ProductLabels_Model_Observer
{
    private bool $attributesAdded = false;

    /**
     * Add the attributes referenced by `{attribute:code}` before the collection loads,
     * so the placeholder costs one extra select column instead of one query per product.
     */
    #[Maho\Config\Observer('catalog_product_collection_load_before')]
    public function addRequiredAttributes(Maho\Event\Observer $observer): void
    {
        if ($this->attributesAdded || !$this->_isEnabled()) {
            return;
        }
        $collection = $observer->getCollection();
        if (!$collection instanceof Mage_Catalog_Model_Resource_Product_Collection) {
            return;
        }
        $codes = Mage::getResourceSingleton('productlabels/rule')->getRequiredAttributes();
        if ($codes !== []) {
            $collection->addAttributeToSelect($codes);
        }
        $this->attributesAdded = true;
    }

    /**
     * One query per page for every product of the collection.
     */
    #[Maho\Config\Observer('catalog_product_collection_load_after')]
    public function attachLabelsToCollection(Maho\Event\Observer $observer): void
    {
        if (!$this->_isEnabled()) {
            return;
        }
        $collection = $observer->getCollection();
        if (!$collection instanceof Mage_Catalog_Model_Resource_Product_Collection) {
            return;
        }
        $products = [];
        $ids = [];
        foreach ($collection as $product) {
            $products[] = $product;
            $ids[] = (int) $product->getId();
        }
        if ($ids === []) {
            return;
        }
        $this->_attach($products, $ids);
    }

    #[Maho\Config\Observer('catalog_controller_product_view')]
    public function attachLabelsToProduct(Maho\Event\Observer $observer): void
    {
        if (!$this->_isEnabled()) {
            return;
        }
        $product = $observer->getProduct();
        if (!$product instanceof Mage_Catalog_Model_Product || !$product->getId()) {
            return;
        }
        $this->_attach([$product], [(int) $product->getId()]);
    }

    /**
     * A single product save is cheap: at most one select per active rule.
     */
    #[Maho\Config\Observer('catalog_product_save_after')]
    public function reindexOnProductSave(Maho\Event\Observer $observer): void
    {
        if (!$this->_isEnabled()) {
            return;
        }
        $product = $observer->getProduct();
        if ($product instanceof Mage_Catalog_Model_Product && $product->getId()) {
            Mage::getSingleton('productlabels/indexer')->reindexList([(int) $product->getId()]);
        }
    }

    /**
     * @param list<Mage_Catalog_Model_Product> $products
     * @param list<int> $ids
     */
    private function _attach(array $products, array $ids): void
    {
        $labels = Mage::getSingleton('productlabels/label')->fetchForProducts($ids);
        $needsQty = false;
        foreach ($labels as $productLabels) {
            foreach ($productLabels as $label) {
                if (str_contains((string) ($label['text'] ?? ''), '{qty}')) {
                    $needsQty = true;
                    break 2;
                }
            }
        }
        $qtys = $needsQty ? $this->_fetchQty($ids) : [];
        foreach ($products as $product) {
            $productId = (int) $product->getId();
            $product->setData('productlabels', $labels[$productId] ?? []);
            if (isset($qtys[$productId])) {
                $product->setData('productlabels_qty', $qtys[$productId]);
            }
        }
    }

    /**
     * @param list<int> $ids
     * @return array<int, float>
     */
    private function _fetchQty(array $ids): array
    {
        $adapter = Mage::helper('productlabels')->getConnection('core_read');
        $select = $adapter->select()
            ->from(
                ['si' => Mage::getSingleton('core/resource')->getTableName('cataloginventory/stock_item')],
                ['product_id', 'qty'],
            )
            ->where('si.product_id IN (?)', $ids);
        $result = [];
        foreach ($adapter->fetchAll($select) as $row) {
            $result[(int) $row['product_id']] = (float) $row['qty'];
        }
        return $result;
    }

    private function _isEnabled(): bool
    {
        return (bool) Mage::getStoreConfig('productlabels/general/enabled');
    }
}
