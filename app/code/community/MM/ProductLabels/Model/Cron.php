<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

final class MM_ProductLabels_Model_Cron
{
    /**
     * Prices, stock and dates change without a product save, so the index is rebuilt nightly.
     */
    #[Maho\Config\CronJob('productlabels_reindex', schedule: '0 2 * * *')]
    public function reindex(): void
    {
        if (!Mage::getStoreConfig('productlabels/general/enabled')) {
            return;
        }
        Mage::getSingleton('productlabels/indexer')->reindexAll();
    }
}
