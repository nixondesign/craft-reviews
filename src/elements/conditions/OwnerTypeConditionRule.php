<?php

namespace nixondesign\reviews\elements\conditions;

use Craft;
use craft\base\BlockElementInterface;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use nixondesign\reviews\elements\db\ReviewQuery;
use nixondesign\reviews\elements\Review;

class OwnerTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('reviews', 'Owner Element Type');
    }

    /**
     * @inerhitdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['ownerType'];
    }

    /**
     * @inheritdoc
     */
    protected function options(): array
    {
        $options = [];

        foreach (Craft::$app->getElements()->getAllElementTypes() as $elementType) {
            /** @var string|ElementInterface $elementType */
            if (!is_subclass_of($elementType, BlockElementInterface::class)) {
                $options[] = [
                    'value' => $elementType,
                    'label' => $elementType::displayName(),
                ];
            }
        }

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ReviewQuery $query */
        $query->ownerType($this->paramValue(fn(string $type) => $type));
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Review $element */
        if (($ownerElement = $element->getOwner()) === null) {
            return false;
        }

        return $this->matchValue($ownerElement::class);
    }
}
