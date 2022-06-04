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
use rynpsc\reviews\Plugin;

class TypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('reviews', 'Review Type');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['type', 'typeId'];
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return !empty(Plugin::getInstance()->getReviewTypes()->getAllReviewTypes());
    }

    /**
     * @inheritdoc
     */
    protected function options(): array
    {
        $types = Plugin::getInstance()->getReviewTypes()->getAllReviewTypes();

        return ArrayHelper::map($types, 'uid', 'name');
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ReviewQuery $query */
        $typesService = Plugin::getInstance()->getReviewTypes();

        $query->typeId($this->paramValue(fn(string $uid) => $typesService->getTypeByUid($uid)->id ?? null));
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Review $element */
        return $this->matchValue($element->getType()->uid);
    }
}
