<?php

namespace rynpsc\reviews\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;

class RatingField extends BaseNativeField
{
    public const ATTRIBUTE = 'rating';

    /**
     * @inheritdoc
     */
    public string $attribute = self::ATTRIBUTE;

    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    protected function defaultLabel(ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('reviews', 'Rating');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::$app->getView()->renderTemplate('reviews/fieldlayoutelements/rating', [
            'name' => 'rating',
            'value' => $this->value($element),
            'maxRating' => $element->getType()->maxRating,
        ]);
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

    /**
     * @inheritdoc
     */
    protected function useFieldset(): bool
    {
        return true;
    }
}
