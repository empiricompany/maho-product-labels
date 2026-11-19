<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

class MM_ProductLabels_Block_Adminhtml_Rule_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new Maho\Data\Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save'),
            'method' => 'post',
            'enctype' => 'multipart/form-data',
        ]);
        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
