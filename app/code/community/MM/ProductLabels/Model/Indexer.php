<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

/**
 * Builds productlabels_index. Runs from cron, the CLI and single product saves,
 * never from a storefront request.
 */
final class MM_ProductLabels_Model_Indexer
{
    private const CHUNK_SIZE = 1000;

    /**
     * @return int products indexed by the active rules
     */
    public function reindexAll(): int
    {
        $adapter = $this->_getWriteAdapter();
        $rules = Mage::getResourceModel('productlabels/rule_collection')->addActiveFilter();
        $activeIds = [];
        $products = 0;
        foreach ($rules as $rule) {
            if (!$rule instanceof MM_ProductLabels_Model_Rule) {
                continue;
            }
            $activeIds[] = (int) $rule->getId();
            $products += $this->reindexRule($rule);
        }
        if ($activeIds === []) {
            $adapter->delete($this->_getIndexTable());
        } else {
            $adapter->delete($this->_getIndexTable(), 'rule_id NOT IN (' . implode(',', $activeIds) . ')');
        }
        $this->_markValid();
        return $products;
    }

    /**
     * @param list<int> $productIds
     */
    public function reindexList(array $productIds): int
    {
        $productIds = array_values(array_unique(array_filter(array_map(intval(...), $productIds))));
        if ($productIds === []) {
            return 0;
        }
        $rules = Mage::getResourceModel('productlabels/rule_collection')->addActiveFilter();
        $products = 0;
        foreach ($rules as $rule) {
            if (!$rule instanceof MM_ProductLabels_Model_Rule) {
                continue;
            }
            $products += $this->reindexRule($rule, $productIds);
        }
        $this->_markValid();
        return $products;
    }

    /**
     * @param list<int>|null $productIds null = replace the whole rule
     */
    public function reindexRule(MM_ProductLabels_Model_Rule $rule, ?array $productIds = null): int
    {
        $ruleId = (int) $rule->getId();
        if ($ruleId <= 0) {
            return 0;
        }
        $adapter = $this->_getWriteAdapter();
        $indexTable = $this->_getIndexTable();
        $websites = $rule->getWebsiteIds() ?: $this->_getAllWebsiteIds();
        $groupTargets = $rule->getCustomerGroupIds() ?: [0];
        $position = $rule->getPosition();
        $priority = $rule->getPriority();

        if ($productIds !== null) {
            $rule->setProductsFilter($productIds);
        }
        $matchedIds = $rule->getMatchingProductIds();

        $adapter->beginTransaction();
        try {
            if ($productIds === null) {
                $adapter->delete($indexTable, $adapter->quoteInto('rule_id = ?', $ruleId));
            } else {
                foreach (array_chunk($productIds, self::CHUNK_SIZE) as $chunk) {
                    $adapter->delete(
                        $indexTable,
                        $adapter->quoteInto('rule_id = ?', $ruleId)
                        . ' AND product_id IN (' . implode(',', $chunk) . ')',
                    );
                }
            }

            $rows = [];
            foreach ($websites as $websiteId) {
                foreach ($groupTargets as $groupId) {
                    foreach ($matchedIds as $productId) {
                        $rows[] = [
                            'rule_id' => $ruleId,
                            'product_id' => (int) $productId,
                            'website_id' => (int) $websiteId,
                            'customer_group_id' => (int) $groupId,
                            'position' => $position,
                            'priority' => $priority,
                        ];
                    }
                }
            }
            foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
                $adapter->insertMultiple($indexTable, $chunk);
            }
            $adapter->commit();
        } catch (Throwable $e) {
            $adapter->rollBack();
            throw $e;
        }
        // Products, not index rows: a row is one product for one website and customer group.
        return count($matchedIds);
    }

    /**
     * Keep the admin index management page in sync with a completed rebuild.
     */
    private function _markValid(): void
    {
        try {
            $adapter = $this->_getWriteAdapter();
            // index_process has no updated_at column: the end of a run is ended_at.
            $adapter->update(
                (string) Mage::getSingleton('core/resource')->getTableName('index/process'),
                [
                    'status' => 'valid',
                    'ended_at' => Mage::app()->getLocale()->formatDateForDb('now'),
                ],
                $adapter->quoteInto('indexer_code = ?', 'productlabels'),
            );
        } catch (Throwable) {
            // The index table exists even when the indexer process row does not.
        }
    }

    /**
     * @return list<int>
     */
    private function _getAllWebsiteIds(): array
    {
        $ids = [];
        foreach (Mage::app()->getWebsites() as $website) {
            $ids[] = (int) $website->getId();
        }
        return $ids;
    }

    private function _getIndexTable(): string
    {
        return (string) Mage::getSingleton('core/resource')->getTableName('productlabels/index');
    }

    private function _getWriteAdapter(): Maho\Db\Adapter\AdapterInterface
    {
        return Mage::helper('productlabels')->getConnection('core_write');
    }
}
