<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

class MM_ProductLabels_Block_Adminhtml_Rule_Edit_Tab_General extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('productlabels')->__('General');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('productlabels')->__('General');
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
        $helper = Mage::helper('productlabels');
        $model = Mage::registry('productlabels_rule');
        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('pl_');
        $fieldset = $form->addFieldset('base_fieldset', ['legend' => $helper->__('Rule Information')]);

        if ($model->getId()) {
            $fieldset->addField('rule_id', 'hidden', ['name' => 'rule_id']);
        }
        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => $helper->__('Name'),
            'class' => 'required-entry',
            'required' => true,
        ]);
        $fieldset->addField('description', 'text', [
            'name' => 'description',
            'label' => $helper->__('Description'),
        ]);
        $fieldset->addField('code', 'text', [
            'name' => 'code',
            'label' => $helper->__('Code'),
            'note' => $helper->__('Generated from the name when empty. Used in templates, CLI and API.'),
        ]);
        $fieldset->addField('status', 'select', [
            'name' => 'status',
            'label' => $helper->__('Status'),
            'values' => [
                ['value' => 0, 'label' => $helper->__('Disabled')],
                ['value' => 1, 'label' => $helper->__('Enabled')],
            ],
        ]);
        $fieldset->addField('website_ids', 'multiselect', [
            'name' => 'website_ids[]',
            'label' => $helper->__('Websites'),
            'values' => $this->_getWebsiteOptions(),
            'note' => $helper->__('Leave empty for all websites.'),
        ]);
        $fieldset->addField('customer_group_ids', 'multiselect', [
            'name' => 'customer_group_ids[]',
            'label' => $helper->__('Customer Groups'),
            'values' => $this->_getCustomerGroupOptions(),
            'note' => $helper->__('Leave empty for all customer groups.'),
        ]);
        $fieldset->addField('from_date', 'date', [
            'name' => 'from_date',
            'label' => $helper->__('Valid From'),
            'format' => 'yyyy-MM-dd',
            'time' => false,
        ]);
        $fieldset->addField('to_date', 'date', [
            'name' => 'to_date',
            'label' => $helper->__('Valid To'),
            'format' => 'yyyy-MM-dd',
            'time' => false,
        ]);
        $fieldset->addField('priority', 'text', [
            'name' => 'priority',
            'label' => $helper->__('Priority'),
            'class' => 'validate-number',
            'note' => $helper->__('Lower runs first, like the sort order of cart rules.'),
        ]);
        $fieldset->addField('stop', 'select', [
            'name' => 'stop',
            'label' => $helper->__('Stop Further Labels'),
            'values' => [
                ['value' => 0, 'label' => $helper->__('No')],
                ['value' => 1, 'label' => $helper->__('Yes')],
            ],
            'note' => $helper->__('When yes, labels with a higher priority on the same position are not shown.'),
        ]);

        $values = $model->getData();
        $values['status'] ??= 1;
        $values['priority'] ??= 0;
        $values['stop'] ??= 0;
        if (empty($values['from_date'])) {
            unset($values['from_date']);
        }
        if (empty($values['to_date'])) {
            unset($values['to_date']);
        }
        $form->setValues($values);
        $this->setForm($form);
        return parent::_prepareForm();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function _getWebsiteOptions(): array
    {
        $options = [];
        foreach (Mage::app()->getWebsites() as $website) {
            $options[] = ['value' => (int) $website->getId(), 'label' => (string) $website->getName()];
        }
        return $options;
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function _getCustomerGroupOptions(): array
    {
        $options = [];
        foreach (Mage::getModel('customer/group')->getCollection() as $group) {
            $options[] = [
                'value' => (int) $group->getId(),
                'label' => (string) $group->getCustomerGroupCode(),
            ];
        }
        return $options;
    }
}
