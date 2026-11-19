<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

/**
 * Look and per-store-view texts. The fieldsets are built here, the layout and the live
 * preview live in the template: one fieldset per store view, grouped by website.
 */
class MM_ProductLabels_Block_Adminhtml_Rule_Edit_Tab_Label extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    private ?Maho\Data\Form\Element\Fieldset $_lookFieldset = null;

    /**
     * Store view cards, in the order they are added.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $_cards = [];

    /**
     * @var list<array{legend: string, code: string, show_title: bool, store_ids: list<int>}>
     */
    private array $_groups = [];

    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('productlabels/rule/edit/tab/label.phtml');
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('productlabels')->__('Label');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('productlabels')->__('Label');
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
        $texts = $model instanceof MM_ProductLabels_Model_Rule ? $model->getTexts() : [];
        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('pl_');
        $form->setFieldNameSuffix('');

        $look = $form->addFieldset('look', [
            'legend' => $helper->__('Look'),
            'comment' => $helper->__('Position and colors are shared by every store view.'),
        ]);
        $look->addField('position', 'select', [
            'name' => 'position',
            'label' => $helper->__('Position'),
            'values' => [
                ['value' => 'top-left', 'label' => $helper->__('Top Left')],
                ['value' => 'top-right', 'label' => $helper->__('Top Right')],
                ['value' => 'bottom-left', 'label' => $helper->__('Bottom Left')],
                ['value' => 'bottom-right', 'label' => $helper->__('Bottom Right')],
                ['value' => 'center', 'label' => $helper->__('Center')],
                ['value' => 'content', 'label' => $helper->__('In Content')],
            ],
        ]);
        $look->addField('text_color', 'color', [
            'name' => 'text_color',
            'label' => $helper->__('Text Color'),
        ]);
        $look->addField('bg_color', 'color', [
            'name' => 'bg_color',
            'label' => $helper->__('Background Color'),
        ]);
        $this->_lookFieldset = $look;

        $this->_cards = [];
        $this->_groups = [];
        $this->_addGroup($form, (string) $helper->__('Default (all store views)'), '', [0], $texts, false);
        foreach (Mage::app()->getWebsites() as $website) {
            if (!$website instanceof Mage_Core_Model_Website) {
                continue;
            }
            $storeIds = [];
            foreach ($website->getStores() as $store) {
                if ($store instanceof Mage_Core_Model_Store) {
                    $storeIds[] = (int) $store->getId();
                }
            }
            if ($storeIds === []) {
                continue;
            }
            $this->_addGroup($form, (string) $website->getName(), (string) $website->getCode(), $storeIds, $texts);
        }

        $values = $model instanceof MM_ProductLabels_Model_Rule ? $model->getData() : [];
        $values['position'] = $model instanceof MM_ProductLabels_Model_Rule ? $model->getPosition() : 'top-left';
        $values['text_color'] = $model instanceof MM_ProductLabels_Model_Rule ? $model->getTextColor() : '';
        $values['bg_color'] = $model instanceof MM_ProductLabels_Model_Rule ? $model->getBgColor() : '';
        foreach (array_keys($this->_cards) as $storeId) {
            $data = is_array($texts[$storeId] ?? null) ? $texts[$storeId] : [];
            $values['text_' . $storeId . '_text'] = $data['text'] ?? '';
        }
        $form->setValues($values);
        $this->setForm($form);
        return parent::_prepareForm();
    }

    public function getLookFieldset(): ?Maho\Data\Form\Element\Fieldset
    {
        return $this->_lookFieldset;
    }

    /**
     * @return list<array{legend: string, code: string, show_title: bool, cards: array<int, array<string, mixed>>}>
     */
    public function getGroups(): array
    {
        $groups = [];
        foreach ($this->_groups as $group) {
            $cards = [];
            foreach ($group['store_ids'] as $storeId) {
                if (isset($this->_cards[$storeId])) {
                    $cards[$storeId] = $this->_cards[$storeId];
                }
            }
            if ($cards !== []) {
                $groups[] = [
                    'legend' => $group['legend'],
                    'code' => $group['code'],
                    'show_title' => $group['show_title'],
                    'cards' => $cards,
                ];
            }
        }
        return $groups;
    }

    /**
     * @return array<int, string> store view id => label shown in the preview selector
     */
    public function getPreviewStores(): array
    {
        $helper = Mage::helper('productlabels');
        $stores = [0 => (string) $helper->__('Default (all store views)')];
        foreach ($this->_cards as $storeId => $card) {
            $stores[(int) $storeId] = (string) $card['legend'];
        }
        return $stores;
    }

    public function getImageUrl(string $image): string
    {
        if ($image === '') {
            return '';
        }
        return rtrim((string) Mage::getBaseUrl('media'), '/') . '/productlabels/' . ltrim($image, '/');
    }

    public function getPreviewConfigJson(): string
    {
        $helper = Mage::helper('productlabels');
        return (string) json_encode(
            [
                'empty' => $helper->__('Empty'),
                'edited' => $helper->__('Edited'),
                'placeholder' => $helper->__('Preview'),
                'imageHint' => $helper->__('An image replaces the text and the colors.'),
                'textColor' => $helper->__('Text Color'),
                'bgColor' => $helper->__('Background Color'),
            ],
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
        );
    }

    /**
     * @param list<int> $storeIds
     * @param array<int, mixed> $texts
     */
    private function _addGroup(
        Maho\Data\Form $form,
        string $legend,
        string $code,
        array $storeIds,
        array $texts,
        bool $showTitle = true,
    ): void {
        $this->_groups[] = [
            'legend' => $legend,
            'code' => $code,
            'show_title' => $showTitle,
            'store_ids' => $storeIds,
        ];
        foreach ($storeIds as $storeId) {
            $this->_addStoreCard($form, $storeId, $texts);
        }
    }

    /**
     * @param array<int, mixed> $texts
     */
    private function _addStoreCard(Maho\Data\Form $form, int $storeId, array $texts): void
    {
        $helper = Mage::helper('productlabels');
        $store = $storeId > 0 ? Mage::app()->getStore($storeId) : false;
        $data = is_array($texts[$storeId] ?? null) ? $texts[$storeId] : [];
        $image = (string) ($data['image'] ?? '');
        $legend = $store instanceof Mage_Core_Model_Store
            ? (string) $store->getName()
            : (string) $helper->__('Default (all store views)');

        $fieldset = $form->addFieldset('store_' . $storeId, ['class' => 'pl-store']);
        $fieldset->addField('text_' . $storeId . '_text', 'text', [
            'name' => 'texts[' . $storeId . '][text]',
            'label' => $helper->__('Text'),
            'note' => $helper->__('Placeholders: {discount_percent}, {discount_amount}, {price}, {final_price}, {qty}, {attribute:code}'),
        ]);
        $fieldset->addField('text_' . $storeId . '_image', 'file', [
            'name' => 'texts[' . $storeId . '][image]',
            'label' => $helper->__('Image'),
            'note' => $image === '' ? '' : $helper->__('Current file: %s', $image),
        ]);
        $fieldset->addField('text_' . $storeId . '_remove_image', 'checkbox', [
            'name' => 'texts[' . $storeId . '][remove_image]',
            'label' => $helper->__('Remove Image'),
            'value' => 1,
        ]);

        $this->_cards[$storeId] = [
            'legend' => $legend,
            'code' => $store instanceof Mage_Core_Model_Store ? (string) $store->getCode() : '',
            'fieldset' => $fieldset,
            'image' => $image,
        ];
    }
}
