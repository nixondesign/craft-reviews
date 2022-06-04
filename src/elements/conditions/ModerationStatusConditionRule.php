<?php

namespace rynpsc\reviews\elements\conditions;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use rynpsc\reviews\elements\db\ReviewQuery;
use rynpsc\reviews\elements\Review;

class ModerationStatusConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inerhitdoc
     */
    public function getLabel(): string
    {
        return Craft::t('reviews', 'Moderation Status');
    }

    /**
     * @inerhitdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['moderationStatus'];
    }

    /**
     * @inerhitdoc
     */
    protected function options(): array
    {
        return ArrayHelper::getColumn(Review::moderationStatuses(), 'label');
    }

    /**
     * @inerhitdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ReviewQuery $query */
        $query->moderationStatus($this->paramValue(fn(string $status) => $status));
    }

    /**
     * @inerhitdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Review $element */
        return $this->matchValue($element->moderationStatus);
    }
}
