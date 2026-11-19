<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

final class MM_ProductLabels_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLabels(Mage_Catalog_Model_Product $product): array
    {
        return Mage::getSingleton('productlabels/label')->getForProduct($product);
    }

    /**
     * Labels drawn over the product image (every position but `content`).
     */
    public function render(Mage_Catalog_Model_Product $product): string
    {
        return (string) Mage::app()->getLayout()
            ->createBlock('productlabels/label')
            ->setProduct($product)
            ->toHtml();
    }

    /**
     * Labels of position `content`, to print in the text block of the card, not in the overlay.
     */
    public function renderContent(Mage_Catalog_Model_Product $product): string
    {
        return (string) Mage::app()->getLayout()
            ->createBlock('productlabels/label')
            ->setProduct($product)
            ->setContentOnly(true)
            ->toHtml();
    }

    /**
     * Typed database connection: getConnection() is declared as AdapterInterface|false.
     */
    public function getConnection(string $type = 'core_read'): Maho\Db\Adapter\AdapterInterface
    {
        $connection = Mage::getSingleton('core/resource')->getConnection($type);
        if (!$connection instanceof Maho\Db\Adapter\AdapterInterface) {
            throw new Mage_Core_Exception('Database connection not available: ' . $type);
        }
        return $connection;
    }
}
