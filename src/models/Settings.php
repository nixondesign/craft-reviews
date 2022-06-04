<?php

namespace rynpsc\reviews\models;

use craft\base\Model;

class Settings extends Model
{
    public bool $showSidebarBadge = true;

    public bool $showRatingElementSources = true;

    public string $honeypotFieldName = 'user_name';

    public bool $enableSpamProtection = true;

    public int $minimumSubmitTime = 1;

    public ?string $submissionTimeFieldName = 'submission_time';

    public bool $showUserReviewsTab = true;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = ['minimumSubmitTime', 'number', 'integerOnly' => true];
        $rules[] = ['honeypotFieldName', 'default', 'value' => 'user_name'];
        $rules[] = ['submissionTimeFieldName', 'default', 'value' => 'submission_time'];

        return $rules;
    }
}
