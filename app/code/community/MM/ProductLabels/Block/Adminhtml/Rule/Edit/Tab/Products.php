<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

/**
 * Tab that shows the read-only products grid of the rule.
 *
 * The tabs block sets the id of its child to the tab id, so the grid lives in a child
 * block: the grid keeps its own id and its JavaScript object keeps working.
 */
class MM_ProductLabels_Block_Adminhtml_Rule_Edit_Tab_Products extends Mage_Adminhtml_Block_Widget implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('productlabels')->__('Products');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('productlabels')->__('Products');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return true;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }

    #[\Override]
    protected function _toHtml(): string
    {
        $rule = Mage::registry('productlabels_rule');
        if (!$rule instanceof MM_ProductLabels_Model_Rule || (int) $rule->getId() === 0) {
            return '<p class="productlabels-grid-note">'
                . $this->escapeHtml($this->__('Save the label to see the products it matches.'))
                . '</p>';
        }

        $note = '<p class="productlabels-grid-note">'
            . $this->escapeHtml(
                $this->__('The list comes from the label index. Save the label or run the indexer to update it.'),
            )
            . '</p>';

        return $note . $this->getChildHtml('grid');
    }
}
