<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

/**
 * Conditions root of a label rule.
 *
 * It re-skins the catalog price rule tree under the 'productlabels/...' factory prefix so every
 * row created by the widget is an MM_ProductLabels_Model_Rule_Condition_Product, which brings the
 * attribute whitelist, the relative date operators and the date comparison of this module.
 */
class MM_ProductLabels_Model_Rule_Condition_Combine extends Mage_CatalogRule_Model_Rule_Condition_Combine
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('productlabels/rule_condition_combine');
    }

    #[\Override]
    protected function _getConditionPrefix(): string
    {
        return 'productlabels/rule_condition';
    }

    #[\Override]
    protected function _getConditionHelper(): string
    {
        return 'productlabels';
    }

    /**
     * Same shape as the catalog price rule selector, with the attribute list coming from the
     * conditions of this module (see MM_ProductLabels_Model_Rule_Condition_Product).
     *
     * @return array<int|string, mixed>
     */
    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        $helper = Mage::helper('productlabels');
        $prefix = $this->_getConditionPrefix();

        $attributes = [];
        foreach ($this->_getAttributeOptions($prefix . '_product') as $code => $label) {
            $attributes[] = [
                'value' => $prefix . '_product|' . $code,
                'label' => (string) $label,
            ];
        }

        return [
            [
                'value' => $prefix . '_combine',
                'label' => $helper->__('Conditions Combination'),
            ],
            [
                'label' => $helper->__('Product Attribute'),
                'value' => $attributes,
            ],
        ];
    }

    /**
     * @return array<string, string> attribute code => frontend label
     */
    private function _getAttributeOptions(string $alias): array
    {
        $condition = Mage::getModel($alias);
        if (!$condition instanceof Mage_Rule_Model_Condition_Product_Abstract) {
            return [];
        }
        /** @var array<string, string> $options */
        $options = (array) $condition->loadAttributeOptions()->getAttributeOption();
        return $options;
    }
}
