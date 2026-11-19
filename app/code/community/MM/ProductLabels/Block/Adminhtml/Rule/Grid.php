<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

class MM_ProductLabels_Block_Adminhtml_Rule_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('productlabelsRuleGrid');
        $this->setDefaultSort('priority');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $this->setCollection(Mage::getResourceModel('productlabels/rule_collection'));
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $helper = Mage::helper('productlabels');
        $this->addColumn('rule_id', [
            'header' => $helper->__('ID'),
            'align' => 'right',
            'width' => '50px',
            'index' => 'rule_id',
        ]);
        $this->addColumn('name', [
            'header' => $helper->__('Name'),
            'index' => 'name',
        ]);
        $this->addColumn('code', [
            'header' => $helper->__('Code'),
            'index' => 'code',
        ]);
        $this->addColumn('position', [
            'header' => $helper->__('Position'),
            'index' => 'position',
            'width' => '120px',
        ]);
        $this->addColumn('priority', [
            'header' => $helper->__('Priority'),
            'index' => 'priority',
            'width' => '80px',
        ]);
        $this->addColumn('status', [
            'header' => $helper->__('Status'),
            'index' => 'status',
            'type' => 'options',
            'width' => '100px',
            'options' => [0 => $helper->__('Disabled'), 1 => $helper->__('Enabled')],
        ]);
        $this->addColumn('action', [
            'header' => $helper->__('Action'),
            'width' => '100px',
            'type' => 'action',
            'getter' => 'getId',
            'actions' => [[
                'caption' => $helper->__('Edit'),
                'url' => ['base' => '*/*/edit'],
                'field' => 'rule_id',
            ]],
            'filter' => false,
            'sortable' => false,
        ]);
        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['rule_id' => $row->getId()]);
    }
}
