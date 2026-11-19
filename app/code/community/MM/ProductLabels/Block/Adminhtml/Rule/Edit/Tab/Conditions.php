<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

/**
 * Same conditions widget as the catalog price rules.
 */
class MM_ProductLabels_Block_Adminhtml_Rule_Edit_Tab_Conditions extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('productlabels')->__('Conditions');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('productlabels')->__('Conditions');
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
    protected function _prepareForm()
    {
        $model = Mage::registry('productlabels_rule');
        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('rule_');

        $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
            ->setTemplate('promo/fieldset.phtml')
            ->setNewChildUrl($this->getUrl('*/*/newConditionHtml/form/rule_conditions_fieldset'));

        $fieldset = $form->addFieldset('conditions_fieldset', [
            'legend' => Mage::helper('productlabels')->__('Conditions (leave blank for all products)'),
        ])->setRenderer($renderer);

        $fieldset->addField('conditions', 'text', [
            'name' => 'conditions',
            'label' => Mage::helper('productlabels')->__('Conditions'),
            'title' => Mage::helper('productlabels')->__('Conditions'),
            'required' => true,
        ])->setRule($model)->setRenderer(Mage::getBlockSingleton('rule/conditions'));

        $form->setValues($model->getData());
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
