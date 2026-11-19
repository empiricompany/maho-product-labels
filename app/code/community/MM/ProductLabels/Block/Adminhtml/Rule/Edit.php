<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

class MM_ProductLabels_Block_Adminhtml_Rule_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->_objectId = 'rule_id';
        $this->_blockGroup = 'productlabels';
        $this->_controller = 'adminhtml_rule';
        $this->_addButton('saveandcontinue', [
            'label' => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class' => 'save',
        ], -100);

        // The button calls a page-level function the framework does not ship: each edit screen
        // defines it, see Maho_Giftcard_Block_Adminhtml_Giftcard_Edit.
        $this->_formScripts[] = "
            function saveAndContinueEdit(){
                const form = document.getElementById('edit_form');
                if (form) {
                    editForm.submit(form.action + 'back/edit/');
                }
            }
        ";
    }

    #[\Override]
    public function getHeaderText()
    {
        $model = Mage::registry('productlabels_rule');
        if ($model && $model->getId()) {
            return Mage::helper('productlabels')->__('Edit Product Label "%s"', $this->escapeHtml((string) $model->getName()));
        }
        return Mage::helper('productlabels')->__('New Product Label');
    }
}
