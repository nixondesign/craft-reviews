<?php

namespace rynpsc\reviews\elements\conditions;

use Craft;
use craft\base\BlockElementInterface;
use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\UrlHelper;

class OwnerConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @var string
     */
    public string $elementType = Entry::class;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('reviews', 'Owner Element');
    }

    /**
     * @inheritdoc
     */
    protected function elementType(): string
    {
        return $this->elementType;
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $elementId = $this->getElementId();

        if ($elementId !== null) {
            $query->ownerId($elementId);
        }
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(): string
    {
        return Html::tag('div',
            Cp::selectHtml([
                'name' => 'elementType',
                'options' => $this->_elementTypeOptions(),
                'value' => $this->elementType,
                'inputAttributes' => [
                    'hx' => [
                        'post' => UrlHelper::actionUrl('conditions/render'),
                    ],
                ],
            ]) .
            parent::inputHtml(),
            [
                'class' => ['flex', 'flex-nowrap'],
            ]
        );
    }

    /**
     * @return array
     */
    private function _elementTypeOptions(): array
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
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['elementType'], 'safe'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'elementType' => $this->elementType,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $elementId = $this->getElementId();

        if (!$elementId) {
            return true;
        }

        return $this->matchValue($element->ownerId);
    }
}
