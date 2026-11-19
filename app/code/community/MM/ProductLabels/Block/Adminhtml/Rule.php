<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

class MM_ProductLabels_Block_Adminhtml_Rule extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'productlabels';
        $this->_controller = 'adminhtml_rule';
        $this->_headerText = Mage::helper('productlabels')->__('Product Labels');
        $this->_addButtonLabel = Mage::helper('productlabels')->__('Add Label');
        parent::__construct();
    }
}
