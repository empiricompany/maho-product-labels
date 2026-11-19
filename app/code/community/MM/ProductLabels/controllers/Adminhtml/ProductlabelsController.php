<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

class MM_ProductLabels_Adminhtml_ProductlabelsController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'catalog/productlabels/manage';

    #[Maho\Config\Route('/admin/productlabels/index')]
    public function indexAction(): void
    {
        $this->_initAction();
        $this->_title(Mage::helper('productlabels')->__('Product Labels'));
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/productlabels/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/productlabels/edit')]
    public function editAction(): void
    {
        $ruleId = (int) $this->getRequest()->getParam('rule_id');
        $model = Mage::getModel('productlabels/rule');
        if ($ruleId > 0) {
            $model->load($ruleId);
            if (!$model->getId()) {
                $this->_getSession()->addError(Mage::helper('productlabels')->__('This label no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }
        // Saved conditions need the fieldset id: the category and sku choosers break without it.
        $model->getConditions()->setJsFormObject('rule_conditions_fieldset');
        Mage::register('productlabels_rule', $model);
        $this->_initAction();
        $this->_title($model->getId()
            ? Mage::helper('productlabels')->__('Edit Product Label')
            : Mage::helper('productlabels')->__('New Product Label'));
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/productlabels/save')]
    public function saveAction(): void
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            $this->_redirect('*/*/');
            return;
        }
        $data = (array) $request->getPost();
        $ruleId = (int) ($data['rule_id'] ?? 0);
        $model = Mage::getModel('productlabels/rule');
        if ($ruleId > 0) {
            $model->load($ruleId);
        }

        try {
            $texts = $this->_collectTexts($data, $ruleId);
            $model->addData([
                'name' => trim((string) ($data['name'] ?? '')),
                'description' => trim((string) ($data['description'] ?? '')),
                'code' => trim((string) ($data['code'] ?? '')),
                'status' => empty($data['status']) ? 0 : 1,
                'position' => (string) ($data['position'] ?? 'top-left'),
                'priority' => (int) ($data['priority'] ?? 0),
                'stop' => empty($data['stop']) ? 0 : 1,
                'text_color' => $this->_normalizeColor($data['text_color'] ?? null),
                'bg_color' => $this->_normalizeColor($data['bg_color'] ?? null),
                'from_date' => ($data['from_date'] ?? '') !== '' ? (string) $data['from_date'] : null,
                'to_date' => ($data['to_date'] ?? '') !== '' ? (string) $data['to_date'] : null,
            ]);
            $model->setData('website_ids', $this->_intList($data['website_ids'] ?? []));
            $model->setData('customer_group_ids', $this->_intList($data['customer_group_ids'] ?? []));
            // The conditions arrive as rule[conditions][...]: loadPost() expects that sub-array,
            // not the whole POST, otherwise the tree is never loaded and the rule saves empty.
            $model->loadPost((array) ($data['rule'] ?? []));
            $model->setAttributesRequired($this->_extractAttributeCodes($texts));
            $model->setTexts($texts);
            $model->save();

            $products = Mage::getSingleton('productlabels/indexer')->reindexRule($model);
            $this->_getSession()->addSuccess(
                Mage::helper('productlabels')->__('The label has been saved. Products labelled: %s.', $products),
            );
        } catch (Throwable $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_getSession()->setFormData($data);
            if ($ruleId > 0) {
                $this->_redirect('*/*/edit', ['rule_id' => $ruleId]);
            } else {
                $this->_redirect('*/*/new');
            }
            return;
        }

        if ($request->getParam('back')) {
            $this->_redirect('*/*/edit', ['rule_id' => $model->getId()]);
            return;
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/productlabels/delete')]
    public function deleteAction(): void
    {
        $ruleId = (int) $this->getRequest()->getParam('rule_id');
        if ($ruleId > 0) {
            try {
                $model = Mage::getModel('productlabels/rule');
                $model->load($ruleId);
                $model->delete();
                $this->_getSession()->addSuccess(Mage::helper('productlabels')->__('The label has been deleted.'));
            } catch (Throwable $e) {
                $this->_getSession()->addError($e->getMessage());
                $this->_redirect('*/*/edit', ['rule_id' => $ruleId]);
                return;
            }
        }
        $this->_redirect('*/*/');
    }

    /**
     * Renders one condition row for the conditions widget (same as catalog rules).
     */
    #[Maho\Config\Route('/admin/productlabels/newConditionHtml')]
    public function newConditionHtmlAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $typeArr = explode('|', str_replace('-', '/', (string) $this->getRequest()->getParam('type')));
        $type = $typeArr[0];

        $model = Mage::getModel($type);
        if (!$model instanceof Mage_Rule_Model_Condition_Abstract) {
            $this->getResponse()->setBody('');
            return;
        }
        $model->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('productlabels/rule'))
            ->setPrefix('conditions');
        if (!empty($typeArr[1])) {
            $model->setData('attribute', $typeArr[1]);
        }
        $model->setJsFormObject((string) $this->getRequest()->getParam('form'));
        $this->getResponse()->setBody($model->asHtmlRecursive());
    }

    /**
     * The products grid of the tab, reloaded by AJAX when the user filters or pages.
     */
    #[Maho\Config\Route('/admin/productlabels/productsGrid')]
    public function productsGridAction(): void
    {
        $rule = Mage::getModel('productlabels/rule');
        $ruleId = (int) $this->getRequest()->getParam('rule_id');
        if ($ruleId > 0) {
            $rule->load($ruleId);
        }
        Mage::register('productlabels_rule', $rule);
        $this->_initAction();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('productlabels/adminhtml_rule_edit_tab_products_grid')->toHtml(),
        );
    }

    /**
     * @return $this
     */
    protected function _initAction(): static
    {
        $this->loadLayout()
            ->_setActiveMenu('catalog/productlabels')
            ->_addBreadcrumb(
                Mage::helper('productlabels')->__('Catalog'),
                Mage::helper('productlabels')->__('Catalog'),
            )
            ->_addBreadcrumb(
                Mage::helper('productlabels')->__('Product Labels'),
                Mage::helper('productlabels')->__('Product Labels'),
            );
        return $this;
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed(self::ADMIN_RESOURCE);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function _collectTexts(array $data, int $ruleId): array
    {
        $posted = is_array($data['texts'] ?? null) ? $data['texts'] : [];
        $existing = $ruleId > 0
            ? Mage::getResourceSingleton('productlabels/rule')->getTextsByRuleId($ruleId)
            : [];
        $storeIds = [0];
        foreach (Mage::app()->getStores() as $store) {
            $storeIds[] = (int) $store->getId();
        }

        $texts = [];
        foreach ($storeIds as $storeId) {
            $row = is_array($posted[$storeId] ?? null) ? $posted[$storeId] : [];
            $image = (string) ($existing[$storeId]['image'] ?? '');
            if (!empty($row['remove_image'])) {
                $image = '';
            }
            $uploaded = $this->_uploadImage($storeId);
            if ($uploaded !== null) {
                $image = $uploaded;
            }
            $texts[$storeId] = [
                'text' => trim((string) ($row['text'] ?? '')),
                'image' => $image,
            ];
        }
        return $texts;
    }

    private function _uploadImage(int $storeId): ?string
    {
        if (empty($_FILES['texts']['name'][$storeId]['image'])) {
            return null;
        }
        $error = (int) ($_FILES['texts']['error'][$storeId]['image'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return null;
        }
        $originalName = (string) $_FILES['texts']['name'][$storeId]['image'];
        $tmpName = (string) $_FILES['texts']['tmp_name'][$storeId]['image'];
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
            throw new Mage_Core_Exception(
                Mage::helper('productlabels')->__('Unsupported image type: %s', $extension),
            );
        }
        $directory = Mage::getBaseDir('media') . DS . 'productlabels';
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new Mage_Core_Exception(Mage::helper('productlabels')->__('Cannot create the media directory.'));
        }
        $fileName = 'label-' . $storeId . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        if (!move_uploaded_file($tmpName, $directory . DS . $fileName)) {
            throw new Mage_Core_Exception(Mage::helper('productlabels')->__('Cannot store the uploaded image.'));
        }
        return $fileName;
    }

    /**
     * @param array<int, array<string, mixed>> $texts
     * @return list<string>
     */
    private function _extractAttributeCodes(array $texts): array
    {
        $codes = [];
        foreach ($texts as $text) {
            if (preg_match_all('/\{attribute:([a-z0-9_]+)\}/i', (string) ($text['text'] ?? ''), $matches) > 0) {
                $codes = array_merge($codes, $matches[1]);
            }
        }
        return array_values(array_unique(array_map(strtolower(...), $codes)));
    }

    private function _normalizeColor(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));
        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : null;
    }

    /**
     * @return list<int>
     */
    private function _intList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (is_numeric($item) && (int) $item > 0) {
                $result[] = (int) $item;
            }
        }
        return array_values(array_unique($result));
    }
}
