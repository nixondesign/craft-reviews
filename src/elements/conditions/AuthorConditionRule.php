<?php

namespace rynpsc\reviews\elements\conditions;

use Craft;
use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use rynpsc\reviews\elements\db\ReviewQuery;
use rynpsc\reviews\elements\Review;

class AuthorConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('reviews', 'Author');
    }

    protected function elementType(): string
    {
        return User::class;
    }

    public function getExclusiveQueryParams(): array
    {
        return ['authorId'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ReviewQuery $query */
        $query->authorId($this->getElementId());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Review $element */
        return $this->matchValue($element->_authorId);
    }
}
