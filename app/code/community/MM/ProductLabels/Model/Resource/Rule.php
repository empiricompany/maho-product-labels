<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

class MM_ProductLabels_Model_Resource_Rule extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('productlabels/rule', 'rule_id');
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        $object->setData('updated_at', $now);
        if (!$object->getId()) {
            $object->setData('created_at', $now);
        }
        if (!$object->getData('code') && $object->getData('name')) {
            $object->setData('code', $this->_slug((string) $object->getData('name')));
        }
        return parent::_beforeSave($object);
    }

    #[\Override]
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        if ($object instanceof MM_ProductLabels_Model_Rule && $object->getId()) {
            $ruleId = (int) $object->getId();
            $object->setData('website_ids', $this->_getRelatedIds('productlabels/rule_website', 'website_id', $ruleId));
            $object->setData(
                'customer_group_ids',
                $this->_getRelatedIds('productlabels/rule_customer_group', 'customer_group_id', $ruleId),
            );
            $object->setData('texts', $this->getTextsByRuleId($ruleId));
        }
        return parent::_afterLoad($object);
    }

    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        if ($object instanceof MM_ProductLabels_Model_Rule && $object->getId()) {
            $ruleId = (int) $object->getId();
            $this->_saveRelatedIds('productlabels/rule_website', 'website_id', $ruleId, $object->getWebsiteIds());
            $this->_saveRelatedIds(
                'productlabels/rule_customer_group',
                'customer_group_id',
                $ruleId,
                $object->getCustomerGroupIds(),
            );
            $this->saveTexts($ruleId, $object->getTexts());
        }
        return parent::_afterSave($object);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTextsByRuleId(int $ruleId): array
    {
        $adapter = Mage::helper('productlabels')->getConnection('core_read');
        $select = $adapter->select()
            ->from($this->getTable('productlabels/rule_text'))
            ->where('rule_id = ?', $ruleId);
        $rows = $adapter->fetchAll($select);
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['store_id']] = $row;
        }
        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $texts
     */
    public function saveTexts(int $ruleId, array $texts): void
    {
        $adapter = Mage::helper('productlabels')->getConnection('core_write');
        $adapter->delete($this->getTable('productlabels/rule_text'), $adapter->quoteInto('rule_id = ?', $ruleId));

        $rows = [];
        foreach ($texts as $storeId => $data) {
            if (!is_array($data)) {
                continue;
            }
            $row = [
                'rule_id' => $ruleId,
                'store_id' => (int) $storeId,
                'text' => $this->_nullIfEmpty($data['text'] ?? null),
                'image' => $this->_nullIfEmpty($data['image'] ?? null),
            ];
            if ($row['text'] === null && $row['image'] === null) {
                continue;
            }
            $rows[] = $row;
        }
        if ($rows !== []) {
            $adapter->insertMultiple($this->getTable('productlabels/rule_text'), $rows);
        }
    }

    /**
     * Attributes referenced by `{attribute:code}` in any active rule text.
     *
     * @return list<string>
     */
    public function getRequiredAttributes(): array
    {
        $adapter = Mage::helper('productlabels')->getConnection('core_read');
        $select = $adapter->select()
            ->from($this->getTable('productlabels/rule'), ['attributes_required'])
            ->where('status = ?', 1);
        $codes = [];
        foreach ($adapter->fetchCol($select) as $raw) {
            foreach (explode(',', (string) $raw) as $code) {
                $code = trim($code);
                if ($code !== '') {
                    $codes[$code] = $code;
                }
            }
        }
        return array_values($codes);
    }

    /**
     * @return list<int>
     */
    private function _getRelatedIds(string $table, string $column, int $ruleId): array
    {
        $adapter = Mage::helper('productlabels')->getConnection('core_read');
        $select = $adapter->select()
            ->from($this->getTable($table), $column)
            ->where('rule_id = ?', $ruleId);
        return array_values(array_map(intval(...), $adapter->fetchCol($select)));
    }

    /**
     * @param list<int> $ids
     */
    private function _saveRelatedIds(string $table, string $column, int $ruleId, array $ids): void
    {
        $adapter = Mage::helper('productlabels')->getConnection('core_write');
        $adapter->delete($this->getTable($table), $adapter->quoteInto('rule_id = ?', $ruleId));
        $rows = [];
        foreach ($ids as $id) {
            if ((int) $id > 0) {
                $rows[] = ['rule_id' => $ruleId, $column => (int) $id];
            }
        }
        if ($rows !== []) {
            $adapter->insertMultiple($this->getTable($table), $rows);
        }
    }

    private function _nullIfEmpty(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function _slug(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        return $slug === '' ? 'label' : substr($slug, 0, 64);
    }
}
