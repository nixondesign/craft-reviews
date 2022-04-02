<?php

namespace rynpsc\reviews\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\StandardField;

class RatingField extends StandardField
{
    /**
     * @inheritdoc
     */
    public function attribute(): string
    {
        return 'rating';
    }

    /**
     * @inheritdoc
     */
    public function mandatory(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(ElementInterface $element = null, bool $static = false)
    {
        return Craft::t('reviews', 'Rating');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(ElementInterface $element = null, bool $static = false)
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
    protected function useFieldset(): bool
    {
        return true;
    }
}
