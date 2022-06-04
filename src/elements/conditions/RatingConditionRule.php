<?php

namespace rynpsc\reviews\elements\conditions;

use rynpsc\reviews\elements\Review;
use rynpsc\reviews\elements\db\ReviewQuery;

use Craft;
use craft\base\ElementInterface;
use craft\base\conditions\BaseNumberConditionRule;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

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
