<?php

namespace rynpsc\reviews\elements\conditions;

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
            ElementConditionRule::class,
            ElementTypeConditionRule::class,
            ModerationStatusConditionRule::class,
            RatingConditionRule::class,
            SubmissionDateConditionRule::class,
            TypeConditionRule::class,
        ]);
    }
}
