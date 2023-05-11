<?php

namespace nixondesign\reviews\elements\conditions;

use Craft;
use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use nixondesign\reviews\elements\db\ReviewQuery;
use nixondesign\reviews\elements\Review;

class AuthorConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inerhitdoc
     */
    public function getLabel(): string
    {
        return Craft::t('reviews', 'Author');
    }

    /**
     * @inerhitdoc
     */
    protected function elementType(): string
    {
        return User::class;
    }

    /**
     * @inerhitdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['authorId'];
    }

    /**
     * @inerhitdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ReviewQuery $query */
        $query->authorId($this->getElementId());
    }

    /**
     * @inerhitdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Review $element */
        return $this->matchValue($element->_authorId);
    }
}
