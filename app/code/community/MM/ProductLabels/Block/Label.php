<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

final class MM_ProductLabels_Block_Label extends Mage_Core_Block_Template
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('productlabels/label.phtml');
    }

    public function getProduct(): ?Mage_Catalog_Model_Product
    {
        $product = $this->getData('product') ?: Mage::registry('current_product');
        return $product instanceof Mage_Catalog_Model_Product ? $product : null;
    }

    private bool $_contentOnly = false;

    /**
     * A label with position `content` does not belong to the image overlay: it is rendered in the
     * text block of the card, by a second call of this block.
     */
    public function setContentOnly(bool $flag = true): self
    {
        $this->_contentOnly = $flag;
        return $this;
    }

    public function isContentOnly(): bool
    {
        return $this->_contentOnly;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLabels(): array
    {
        $product = $this->getProduct();
        if (!$product) {
            return [];
        }
        $labels = Mage::getSingleton('productlabels/label')->getForProduct($product);
        return array_values(array_filter(
            $labels,
            fn(array $label): bool => $this->isContentOnly()
                ? ($label['position'] ?? '') === 'content'
                : ($label['position'] ?? '') !== 'content',
        ));
    }

    /**
     * @param array<string, mixed> $label
     */
    public function getLabelHtml(array $label): string
    {
        $product = $this->getProduct();
        if (!$product) {
            return '';
        }
        $text = Mage::getSingleton('productlabels/label')->renderText($label, $product);
        $image = (string) ($label['image'] ?? '');

        if ($image !== '') {
            return '<img src="' . $this->escapeHtml($this->getLabelImageUrl($image)) . '"'
                . ' alt="' . $this->escapeHtml($text) . '" loading="lazy" decoding="async">';
        }
        return $text === '' ? '' : (string) $this->escapeHtml($text);
    }

    /**
     * The `badge` class is the theme component: it supplies height, padding, radius, border and
     * font size, so a label looks like the other badges of the storefront. The module CSS only
     * maps the rule colors onto the badge variables.
     *
     * @param array<string, mixed> $label
     */
    public function getLabelCssClass(array $label): string
    {
        $position = strtolower((string) preg_replace('/[^a-z-]/i', '', (string) ($label['position'] ?? '')));
        $class = 'badge pl-label pl-label--' . ($position === '' ? 'top-left' : $position);
        return (string) ($label['image'] ?? '') === '' ? $class : $class . ' pl-label--image';
    }

    /**
     * Colors apply to text labels only: an image label carries its own look.
     *
     * @param array<string, mixed> $label
     */
    public function getLabelStyle(array $label): string
    {
        if ((string) ($label['image'] ?? '') !== '') {
            return '';
        }
        $styles = [];
        foreach (['text_color' => '--pl-fg', 'bg_color' => '--pl-bg'] as $key => $property) {
            $value = strtolower(trim((string) ($label[$key] ?? '')));
            if (preg_match('/^#[0-9a-f]{6}$/', $value) === 1) {
                $styles[] = $property . ':' . $value;
            }
        }
        return $styles === [] ? '' : ' style="' . implode(';', $styles) . '"';
    }

    public function getLabelImageUrl(string $image): string
    {
        return rtrim((string) Mage::getBaseUrl('media'), '/') . '/productlabels/' . ltrim($image, '/');
    }
}
