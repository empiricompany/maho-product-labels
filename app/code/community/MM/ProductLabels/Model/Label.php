<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

/**
 * Runtime side of the labels: one indexed query per page, then placeholder rendering
 * from values the product already carries. No query is issued per product.
 */
final class MM_ProductLabels_Model_Label
{
    /** @var array<int, array<int, array<string, mixed>>> */
    private array $resolved = [];

    /**
     * @param list<int> $productIds
     * @return array<int, array<int, array<string, mixed>>> labels per product id, sorted by priority
     */
    public function fetchForProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $adapter = Mage::helper('productlabels')->getConnection('core_read');
        $store = Mage::app()->getStore();
        $storeId = $store instanceof Mage_Core_Model_Store ? (int) $store->getId() : 0;
        $websiteId = $store instanceof Mage_Core_Model_Store ? (int) $store->getWebsiteId() : 0;
        $groupId = (int) Mage::getSingleton('customer/session')->getCustomerGroupId();
        $today = Mage::app()->getLocale()->formatDateForDb('now', withTime: false);

        $textColumns = ['text', 'image'];
        $defaultColumns = [];
        foreach ($textColumns as $column) {
            $defaultColumns[$column . '_default'] = $column;
        }

        $select = $adapter->select()
            ->from(
                ['i' => Mage::getSingleton('core/resource')->getTableName('productlabels/index')],
                ['product_id', 'rule_id', 'position', 'priority'],
            )
            ->joinInner(
                ['r' => Mage::getSingleton('core/resource')->getTableName('productlabels/rule')],
                'r.rule_id = i.rule_id',
                ['stop', 'text_color', 'bg_color'],
            )
            ->joinLeft(
                ['ts' => Mage::getSingleton('core/resource')->getTableName('productlabels/rule_text')],
                'ts.rule_id = i.rule_id AND ts.store_id = ' . $storeId,
                $textColumns,
            )
            ->joinLeft(
                ['td' => Mage::getSingleton('core/resource')->getTableName('productlabels/rule_text')],
                'td.rule_id = i.rule_id AND td.store_id = 0',
                $defaultColumns,
            )
            ->where('i.product_id IN (?)', $productIds)
            ->where('i.website_id = ?', $websiteId)
            ->where('i.customer_group_id IN (?)', [0, $groupId])
            ->where('r.status = ?', 1)
            ->where('(r.from_date IS NULL OR r.from_date <= ?)', $today)
            ->where('(r.to_date IS NULL OR r.to_date >= ?)', $today)
            ->order('i.priority ASC')
            ->order('i.rule_id ASC');

        $rows = $adapter->fetchAll($select);
        $result = [];
        $stopped = [];
        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $position = (string) $row['position'];
            if (isset($stopped[$productId][$position])) {
                continue;
            }
            $label = $this->_mergeText($row);
            if ($label === null) {
                continue;
            }
            $label['rule_id'] = (int) $row['rule_id'];
            $label['position'] = $position;
            $label['priority'] = (int) $row['priority'];
            $result[$productId][] = $label;
            if ((bool) $row['stop']) {
                $stopped[$productId][$position] = true;
            }
        }
        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForProduct(Mage_Catalog_Model_Product $product): array
    {
        $attached = $product->getData('productlabels');
        if (is_array($attached)) {
            $this->_ensureQty($attached, $product);
            return $attached;
        }
        $productId = (int) $product->getId();
        if (!array_key_exists($productId, $this->resolved)) {
            $fetched = $this->fetchForProducts([$productId]);
            $this->resolved[$productId] = $fetched[$productId] ?? [];
        }
        $this->_ensureQty($this->resolved[$productId], $product);
        return $this->resolved[$productId];
    }

    /**
     * The collection observer sets the quantity for the whole page in one query; a single
     * product loaded outside a collection reads its stock item once, only if needed.
     *
     * @param array<int, array<string, mixed>> $labels
     */
    private function _ensureQty(array $labels, Mage_Catalog_Model_Product $product): void
    {
        foreach ($labels as $label) {
            if (str_contains((string) ($label['text'] ?? ''), '{qty}')) {
                if ($product->getData('productlabels_qty') === null) {
                    $stockItem = $product->getStockItem();
                    $product->setData('productlabels_qty', $stockItem ? (float) $stockItem->getQty() : 0.0);
                }
                return;
            }
        }
    }


    /**
     * @param array<string, mixed> $label
     */
    public function renderText(array $label, Mage_Catalog_Model_Product $product): string
    {
        $text = (string) ($label['text'] ?? '');
        if ($text === '') {
            return '';
        }
        $text = (string) preg_replace_callback(
            '/\{attribute:([a-z0-9_]+)\}/i',
            fn(array $m): string => $this->_attributeValue($product, $m[1]),
            $text,
        );
        $text = str_replace('{discount_percent}', $this->_discountPercent($product), $text);
        $text = str_replace('{discount_amount}', $this->_discountAmount($product), $text);
        $text = str_replace('{price}', $this->_currency($product->getPrice()), $text);
        $text = str_replace('{final_price}', $this->_currency($this->_finalPrice($product)), $text);
        $text = str_replace('{qty}', $this->_qty($product), $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function _mergeText(array $row): ?array
    {
        $label = [];
        foreach (['text', 'image'] as $column) {
            $value = $row[$column] ?? null;
            if ($value === null || $value === '') {
                $value = $row[$column . '_default'] ?? null;
            }
            $label[$column] = $value === null || $value === '' ? null : (string) $value;
        }
        $label['text_color'] = $row['text_color'] === null ? null : (string) $row['text_color'];
        $label['bg_color'] = $row['bg_color'] === null ? null : (string) $row['bg_color'];
        if ($label['text'] === null && $label['image'] === null) {
            return null;
        }
        return $label;
    }

    private function _discountPercent(Mage_Catalog_Model_Product $product): string
    {
        $value = $this->_discountPercentValue($product);
        return $value === null ? '' : (string) (int) round($value);
    }

    private function _discountPercentValue(Mage_Catalog_Model_Product $product): ?float
    {
        $price = (float) $product->getPrice();
        $final = $this->_finalPrice($product);
        if ($price <= 0 || $final <= 0 || $final >= $price) {
            return null;
        }
        return (($price - $final) / $price) * 100;
    }

    private function _discountAmount(Mage_Catalog_Model_Product $product): string
    {
        $price = (float) $product->getPrice();
        $final = $this->_finalPrice($product);
        if ($price <= 0 || $final <= 0 || $final >= $price) {
            return '';
        }
        return $this->_currency($price - $final);
    }

    private function _finalPrice(Mage_Catalog_Model_Product $product): float
    {
        $indexed = $product->getData('final_price');
        if ($indexed !== null && $indexed !== '') {
            return (float) $indexed;
        }
        return (float) $product->getFinalPrice();
    }

    private function _qty(Mage_Catalog_Model_Product $product): string
    {
        $qty = $product->getData('productlabels_qty');
        if ($qty === null) {
            $stockItem = $product->getData('stock_item');
            $qty = $stockItem ? $stockItem->getQty() : null;
        }
        return $qty === null ? '' : (string) (int) $qty;
    }

    private function _attributeValue(Mage_Catalog_Model_Product $product, string $code): string
    {
        $value = $product->getData($code);
        if ($value === null || $value === '') {
            return '';
        }

        $text = $product->getAttributeText($code);
        if (is_array($text)) {
            $text = $this->_joinValues($text);
        }
        if (is_string($text) && $text !== '') {
            return $text;
        }

        // Text, textarea and decimal attributes have no source model: getAttributeText()
        // answers with [] or '', so the stored value is the one to print.
        if (is_array($value)) {
            return $this->_joinValues($value);
        }
        if (!is_scalar($value)) {
            return '';
        }

        $resource = $product->getResource();
        $attribute = $resource instanceof Mage_Catalog_Model_Resource_Product ? $resource->getAttribute($code) : false;
        if ($attribute instanceof Mage_Eav_Model_Entity_Attribute_Abstract) {
            $input = (string) $attribute->getFrontendInput();
            if ($input === 'price') {
                return (string) Mage::helper('core')->currency((float) $value, true, false);
            }
            if ($input === 'boolean') {
                $label = $this->_booleanLabel($attribute, (string) $value);
                if ($label !== '') {
                    return $label;
                }
            }
        }

        return $this->_clean((string) $value);
    }

    /**
     * @param array<mixed> $values
     */
    private function _joinValues(array $values): string
    {
        $parts = [];
        foreach ($values as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $part = $this->_clean((string) $item);
            if ($part !== '') {
                $parts[] = $part;
            }
        }
        return implode(', ', $parts);
    }

    private function _clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
    }

    private function _booleanLabel(Mage_Eav_Model_Entity_Attribute_Abstract $attribute, string $value): string
    {
        $source = $attribute->getSource();
        if (!$source instanceof Mage_Eav_Model_Entity_Attribute_Source_Boolean) {
            return '';
        }
        $label = $source->getOptionText($value);
        return is_string($label) ? $this->_clean($label) : '';
    }

    private function _currency(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return (string) Mage::helper('core')->currency((float) $value, true, false);
    }
}
