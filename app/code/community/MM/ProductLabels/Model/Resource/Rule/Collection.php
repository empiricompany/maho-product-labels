<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

class MM_ProductLabels_Model_Resource_Rule_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('productlabels/rule');
    }

    /**
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('status', 1);
        return $this;
    }
}
