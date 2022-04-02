<?php

namespace rynpsc\reviews\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\StandardField;

class ReviewField extends StandardField
{
    /**
     * @inheritdoc
     */
    public function attribute(): string
    {
        return 'review';
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
    protected function defaultLabel(ElementInterface $element = null, bool $static = false): string
    {
        return Craft::t('reviews', 'Review');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(ElementInterface $element = null, bool $static = false): string
    {
        return Craft::$app->getView()->renderTemplate('_includes/forms/textarea', [
            'id' => $this->id(),
            'name' => 'review',
            'value' => $this->value($element),
            'rows' => 15,
        ]);
    }
}
