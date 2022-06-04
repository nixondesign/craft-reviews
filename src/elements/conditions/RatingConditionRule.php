<?php

namespace rynpsc\reviews\elements\conditions;

use Craft;
use craft\base\conditions\BaseNumberConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use rynpsc\reviews\elements\db\ReviewQuery;
use rynpsc\reviews\elements\Review;

class RatingConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inerhitdoc
     */
    public function getLabel(): string
    {
        return Craft::t('reviews', 'Rating');
    }

    /**
     * @inerhitdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['rating'];
    }

    /**
     * @inerhitdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ReviewQuery $query */
        $query->rating($this->paramValue());
    }

    /**
     * @inerhitdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Review $element */
        return $this->matchValue($element->rating);
    }
}
