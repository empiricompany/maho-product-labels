<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

/**
 * Exposes the label index in admin > System > Index Management.
 */
class MM_ProductLabels_Model_Indexer_Product extends Mage_Index_Model_Indexer_Abstract
{
    #[\Override]
    protected $_matchedEntities = [
        Mage_Catalog_Model_Product::ENTITY => [
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_MASS_ACTION,
            Mage_Index_Model_Event::TYPE_DELETE,
        ],
    ];

    #[\Override]
    public function getName(): string
    {
        return Mage::helper('productlabels')->__('Product Labels');
    }

    #[\Override]
    public function getDescription(): string
    {
        return Mage::helper('productlabels')->__('Labels matched by rule, website and customer group');
    }

    #[\Override]
    public function reindexAll(): void
    {
        Mage::getSingleton('productlabels/indexer')->reindexAll();
    }

    #[\Override]
    protected function _registerEvent(Mage_Index_Model_Event $event): void {}

    #[\Override]
    protected function _processEvent(Mage_Index_Model_Event $event): void {}
}
