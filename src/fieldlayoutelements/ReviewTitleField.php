<?php

namespace nixondesign\reviews\fieldlayoutelements;

use craft\base\ElementInterface;
use craft\fieldlayoutelements\TitleField;
use craft\helpers\Html;
use InvalidArgumentException;
use nixondesign\reviews\elements\Review;

class ReviewTitleField extends TitleField
{
    /**
     * @inheritdoc
     */
    protected function selectorInnerHtml(): string
    {
        return Html::tag('span', '', [
            'class' => ['fld-title-field-icon', 'fld-field-hidden', 'hidden'],
        ]) . parent::selectorInnerHtml();
    }

    /**
     * @inheritdoc
     */
    public function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Review) {
            throw new InvalidArgumentException('ReviewTitleField can only be used in review field layouts.');
        }

        if (!$element->getType()->hasTitleField && !$element->hasErrors('title')) {
            return null;
        }

        return parent::inputHtml($element, $static);
    }
}
