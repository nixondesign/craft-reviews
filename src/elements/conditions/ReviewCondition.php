<?php

namespace nixondesign\reviews\elements\conditions;

use craft\elements\conditions\ElementCondition;

class ReviewCondition extends ElementCondition
{
    /**
     * @inerhitdoc
     */
    protected function conditionRuleTypes(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            AuthorConditionRule::class,
            ModerationStatusConditionRule::class,
            OwnerConditionRule::class,
            OwnerTypeConditionRule::class,
            RatingConditionRule::class,
            SubmissionDateConditionRule::class,
            TypeConditionRule::class,
        ]);
    }
}
