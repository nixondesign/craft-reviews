<?php

namespace rynpsc\reviews\elements\conditions;

use Craft;
use craft\base\conditions\BaseDateRangeConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use rynpsc\reviews\elements\db\ReviewQuery;
use rynpsc\reviews\elements\Review;

class SubmissionDateConditionRule extends BaseDateRangeConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('reviews', 'Submission Date');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['submissionDate'];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ReviewQuery $query */
        $query->submissionDate($this->queryParamValue());
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Review $element */
        return $this->matchValue($element->submissionDate);
    }
}
