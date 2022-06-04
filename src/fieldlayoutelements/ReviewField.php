<?php

namespace rynpsc\reviews\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\TextareaField;

class ReviewField extends TextareaField
{
    public const ATTRIBUTE = 'review';

    /**
     * @inheritdoc
     */
    public string $attribute = self::ATTRIBUTE;

    /**
     * @inheritdoc
     */
    public bool $requirable = true;

    /**
     * @inheritdoc
     */
    protected function defaultLabel(ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('reviews', 'Review');
    }

    /**
     * @inheritdoc
     */
    protected function statusClass(?ElementInterface $element = null, bool $static = false): ?string
    {
        if ($element && ($status = $element->getAttributeStatus(self::ATTRIBUTE))) {
            return $status[0];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    protected function statusLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        if ($element && ($status = $element->getAttributeStatus(self::ATTRIBUTE))) {
            return $status[1];
        }

        return null;
    }
}
