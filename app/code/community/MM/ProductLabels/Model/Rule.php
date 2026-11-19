<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

/**
 * A label rule. Conditions use the framework rule engine and the catalog price rule
 * condition tree, so the admin UI is the same as the catalog rules.
 */
class MM_ProductLabels_Model_Rule extends Mage_Rule_Model_Abstract
{
    /**
     * @var array<int, int>|null
     */
    protected $_productIds;

    /**
     * @var list<int>|null
     */
    protected $_productsFilter;

    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->_init('productlabels/rule');
        $this->setIdFieldName('rule_id');
    }

    #[\Override]
    public function getConditionsInstance()
    {
        return Mage::getModel('productlabels/rule_condition_combine');
    }

    /**
     * Labels have no actions; the abstract rule requires the instance.
     */
    #[\Override]
    public function getActionsInstance()
    {
        return Mage::getModel('catalogrule/rule_action_collection');
    }

    /**
     * @param list<int> $productIds
     */
    public function setProductsFilter(array $productIds): static
    {
        $this->_productIds = null;
        $this->_productsFilter = $productIds;
        return $this;
    }

    /**
     * @return list<int>
     */
    public function getMatchingProductIds(): array
    {
        if ($this->_productIds === null) {
            $this->_productIds = [];
            $this->setCollectedAttributes([]);

            $productCollection = Mage::getResourceModel('catalog/product_collection');
            if ($this->getWebsiteIds() !== []) {
                $productCollection->addWebsiteFilter($this->getWebsiteIds());
            }
            if ($this->_productsFilter !== null && $this->_productsFilter !== []) {
                $productCollection->addIdFilter($this->_productsFilter);
            }
            $conditions = $this->getConditions();
            if ($conditions instanceof Mage_CatalogRule_Model_Rule_Condition_Combine) {
                $conditions->collectValidatedAttributes($productCollection);
            }

            Mage::getSingleton('core/resource_iterator')->walk(
                $productCollection->getSelect(),
                [$this->callbackValidateProduct(...)],
                [
                    'attributes' => $this->getCollectedAttributes(),
                    'product' => Mage::getModel('catalog/product'),
                    'store_id' => $this->_getEvaluationStoreId(0),
                ],
            );
        }
        return array_values($this->_productIds ?? []);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function callbackValidateProduct(array $args): void
    {
        $product = $args['product'] ?? null;
        if (!$product instanceof Mage_Catalog_Model_Product) {
            return;
        }
        $product = clone $product;
        $product->setData((array) ($args['row'] ?? []));
        if (!$product->getId()) {
            return;
        }
        $product->setStoreId((int) ($args['store_id'] ?? 0));
        if ($this->getConditions()->validate($product)) {
            $this->_productIds[] = (int) $product->getId();
        }
    }

    private function _getEvaluationStoreId(int $fallbackStoreId): int
    {
        $websiteId = $this->getWebsiteIds()[0] ?? 0;
        $website = $websiteId > 0 ? Mage::app()->getWebsite($websiteId) : Mage::app()->getWebsite(true);
        $store = $website ? $website->getDefaultStore() : null;
        return $store ? (int) $store->getId() : $fallbackStoreId;
    }

    #[\Override]
    public function getId(): ?int
    {
        $value = $this->getData('rule_id');
        return $value === null ? null : (int) $value;
    }

    public function getCode(): ?string
    {
        $value = $this->getData('code');
        return $value === null ? null : (string) $value;
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function getPosition(): string
    {
        return (string) ($this->getData('position') ?: 'top-left');
    }

    public function getPriority(): int
    {
        return (int) $this->getData('priority');
    }

    public function getStop(): bool
    {
        return (bool) $this->getData('stop');
    }

    public function getFromDate(): ?string
    {
        $value = $this->getData('from_date');
        return $value === null || $value === '' ? null : (string) $value;
    }

    public function getToDate(): ?string
    {
        $value = $this->getData('to_date');
        return $value === null || $value === '' ? null : (string) $value;
    }

    public function getTextColor(): ?string
    {
        $value = $this->getData('text_color');
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) === 1 ? strtolower($value) : null;
    }

    public function getBgColor(): ?string
    {
        $value = $this->getData('bg_color');
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) === 1 ? strtolower($value) : null;
    }

    /**
     * @return list<string>
     */
    public function getAttributesRequired(): array
    {
        $raw = (string) $this->getData('attributes_required');
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map(trim(...), explode(',', $raw))));
    }

    /**
     * @param list<string> $codes
     */
    public function setAttributesRequired(array $codes): static
    {
        $codes = array_values(array_unique(array_filter(array_map(trim(...), $codes))));
        return $this->setData('attributes_required', implode(',', $codes));
    }

    /**
     * @return list<int>
     */
    #[\Override]
    public function getWebsiteIds(): array
    {
        return $this->_toIntList($this->getData('website_ids'));
    }

    /**
     * @return list<int>
     */
    #[\Override]
    public function getCustomerGroupIds(): array
    {
        return $this->_toIntList($this->getData('customer_group_ids'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTexts(): array
    {
        $texts = $this->getData('texts');
        return is_array($texts) ? $texts : [];
    }

    /**
     * @param array<int, array<string, mixed>> $texts
     */
    public function setTexts(array $texts): static
    {
        return $this->setData('texts', $texts);
    }

    public function isActive(): bool
    {
        if (!$this->getData('status')) {
            return false;
        }
        $today = Mage::app()->getLocale()->formatDateForDb('now', withTime: false);
        $from = $this->getFromDate();
        $to = $this->getToDate();
        if ($from !== null && $today < $from) {
            return false;
        }
        return $to === null || $today <= $to;
    }

    /**
     * @return list<int>
     */
    private function _toIntList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_map(intval(...), $value));
    }
}
