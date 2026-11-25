<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ProductLabels
 */

declare(strict_types=1);

/**
 * Product attribute condition of a label rule.
 *
 * Date attributes get two extra operators, "is on or after today" and "is on or before today":
 * with a normal operator the value is a date picked from the date picker, with a relative
 * operator the value is a number of days from today (0 = today, negative = past). The nightly
 * indexer re-evaluates the condition, so a relative one follows the calendar on its own.
 *
 * Two departures from the catalog price rule condition:
 *
 * - Attribute list: labels are not price rules, so the list is not filtered by
 *   "is_used_for_promo_rules" (a flag the merchant sets per attribute for cart/catalog price
 *   rules, and which leaves the selector empty when nobody set it). Attributes are filtered by
 *   visibility and input type instead, via isAllowedForRuleCondition().
 * - Date comparison: the engine converts the product value to a Unix timestamp with strtotime()
 *   but compares it with the raw "Y-m-d" string of the condition, so in PHP 8 "<", ">" and ">="
 *   return the wrong answer and an empty product value matches everything.
 */
class MM_ProductLabels_Model_Rule_Condition_Product extends Mage_CatalogRule_Model_Rule_Condition_Product
{
    public const OPERATOR_TODAY_GE = 'today_ge';
    public const OPERATOR_TODAY_LE = 'today_le';

    private const RELATIVE_OPERATORS = [self::OPERATOR_TODAY_GE, self::OPERATOR_TODAY_LE];

    public function __construct()
    {
        parent::__construct();
        $this->setType('productlabels/rule_condition_product');
    }

    #[\Override]
    public function loadAttributeOptions()
    {
        $attributes = [];
        $productAttributes = Mage::getResourceSingleton('catalog/product')
            ->loadAllAttributes()
            ->getAttributesByCode();

        foreach ($productAttributes as $attribute) {
            /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
            if (!$attribute->isAllowedForRuleCondition()) {
                continue;
            }
            $code = $attribute->getAttributeCode();
            if (!is_string($code) || $code === '') {
                continue;
            }
            $label = $attribute->getFrontendLabel();
            if (is_array($label)) {
                $label = reset($label);
            }
            $attributes[$code] = (string) $label;
        }

        $this->_addSpecialAttributes($attributes);
        asort($attributes);
        $this->setAttributeOption($attributes);

        return $this;
    }

    /**
     * Adds the relative date operators to the date input types.
     */
    #[\Override]
    public function loadOperatorOptions()
    {
        parent::loadOperatorOptions();

        $helper = Mage::helper('productlabels');
        $options = (array) $this->getOperatorOption();
        $options[self::OPERATOR_TODAY_GE] = $helper->__('is on or after today');
        $options[self::OPERATOR_TODAY_LE] = $helper->__('is on or before today');
        $this->setOperatorOption($options);

        $byType = (array) $this->getOperatorByInputType();
        foreach (['date', 'datetime'] as $type) {
            if (isset($byType[$type]) && is_array($byType[$type])) {
                $byType[$type][] = self::OPERATOR_TODAY_GE;
                $byType[$type][] = self::OPERATOR_TODAY_LE;
            }
        }
        $this->setOperatorByInputType($byType);

        return $this;
    }

    public function isRelativeOperator(): bool
    {
        return in_array($this->getOperator(), self::RELATIVE_OPERATORS, true);
    }

    /**
     * Relative operators compare as their absolute counterpart, with the value resolved to a
     * timestamp by _comparisonTimestamp().
     */
    #[\Override]
    public function getOperatorForValidate()
    {
        return match ($this->getOperator()) {
            self::OPERATOR_TODAY_GE => '>=',
            self::OPERATOR_TODAY_LE => '<=',
            default => parent::getOperatorForValidate(),
        };
    }

    /**
     * Rule view, kept in sync with what conditions.js writes after the row is applied: the value
     * itself, or "pick a date" when a date attribute has no value yet ("..." said nothing).
     */
    #[\Override]
    public function getValueName(): string
    {
        if ($this->getInputType() !== 'date') {
            return parent::getValueName();
        }
        if ($this->isRelativeOperator()) {
            return (string) $this->_getDays();
        }

        $value = $this->getValue();
        if ($value === null || $value === '') {
            return Mage::helper('productlabels')->__('pick a date');
        }
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A date attribute is a plain text field: it holds the picked date for the absolute operators
     * or the day offset for the relative ones, so the value is always the one that gets compared
     * (and the one the widget prints back after the row is applied).
     */
    #[\Override]
    public function getValueElementType()
    {
        return $this->getInputType() === 'date' ? 'text' : parent::getValueElementType();
    }

    /**
     * The framework formats the value of a date input as a date, which a day offset is not.
     *
     * @return string|int|float|bool|array<mixed>|null
     */
    #[\Override]
    public function getValue()
    {
        if ($this->getInputType() === 'date') {
            return $this->getData('value');
        }
        return parent::getValue();
    }

    /**
     * Renders the date picker of a date attribute. Only one of the two fields is shown: the picker
     * for the absolute operators (it copies the date into the value field, which stays hidden) and
     * the value field for the relative ones, where it holds the day offset.
     */
    #[\Override]
    public function getValueAfterElementHtml(): string
    {
        $html = parent::getValueAfterElementHtml();
        if ($this->getInputType() !== 'date') {
            return $html;
        }

        $helper = Mage::helper('productlabels');
        $prefix = (string) $this->getPrefix();
        $elementId = (string) $this->getId();
        $relative = $this->isRelativeOperator();

        // Inline style: the conditions widget is a one-off row control and the admin theme does
        // not ship utilities for it.
        $style = 'margin-left:.5rem;display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap'
            . ($relative ? ';display:none' : '');

        return $html . '<input type="date" class="pl-rel-date" style="' . $style . '"'
            . ' data-target="' . $helper->escapeHtml($prefix . '__' . $elementId . '__value') . '"'
            . ' data-empty-label="' . $helper->escapeHtml($helper->__('pick a date')) . '"'
            . ' value="' . $helper->escapeHtml($relative ? '' : (string) $this->getData('value')) . '">';
    }

    /**
     * Date attributes are compared as Unix timestamps.
     *
     * The engine passes the product value through strtotime() but keeps the condition value as a
     * string, so PHP 8 compares an integer with a string and returns the wrong answer. An empty
     * product value must not match either ("'' <= ''" would).
     *
     * @param mixed $validatedValue
     */
    #[\Override]
    public function validateAttribute($validatedValue)
    {
        if ($this->getInputType() !== 'date') {
            return parent::validateAttribute($validatedValue);
        }

        $comparison = $this->_comparisonTimestamp();
        $productValue = $this->_toTimestamp($validatedValue);
        if ($comparison === null || $productValue === null) {
            return false;
        }

        $this->setValueParsed($comparison);
        return parent::validateAttribute($productValue);
    }

    /**
     * Condition value as a Unix timestamp: today shifted by the offset for the relative
     * operators, the picked date otherwise. Null when the value cannot be read as a date.
     */
    protected function _comparisonTimestamp(): ?int
    {
        if ($this->isRelativeOperator()) {
            return $this->_limitDate($this->_getDays())->getTimestamp();
        }

        $raw = $this->getData('value');
        if (!is_scalar($raw) || trim((string) $raw) === '') {
            return null;
        }
        $timestamp = strtotime(trim((string) $raw));
        return $timestamp === false ? null : $timestamp;
    }

    /**
     * Product attribute value as a Unix timestamp (the engine may pass either form).
     */
    protected function _toTimestamp(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $timestamp = strtotime(trim($value));
        return $timestamp === false ? null : $timestamp;
    }

    private function _getDays(): int
    {
        $value = $this->getData('value');
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Midnight of the target day, extended to the end of the day for "<=" so that "up to today"
     * includes the whole day.
     */
    private function _limitDate(int $days): \DateTimeImmutable
    {
        $date = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        if ($days !== 0) {
            $date = $date->modify(sprintf('%+d days', $days));
        }
        if ($this->getOperator() === self::OPERATOR_TODAY_LE) {
            $date = $date->setTime(23, 59, 59);
        }
        return $date;
    }
}
