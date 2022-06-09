<?php

namespace rynpsc\reviews\models;

use craft\base\Model;

class Settings extends Model
{
    /**
     * @var bool Show sidebar badge.
     */
    public bool $showSidebarBadge = true;

    /**
     * @var bool Show rating element sources.
     */
    public bool $showRatingElementSources = true;

    /**
     * @var string The name of the honeypot field.
     */
    public string $honeypotFieldName = 'user-name';

    /**
     * @var bool Enable spam protection.
     */
    public bool $enableSpamProtection = true;

    /**
     * @var int Minimum submit time.
     */
    public int $minimumSubmitTime = 1;

    /**
     * @var string|null The name of the submission time field.
     */
    public ?string $submissionTimeFieldName = 'submission-time';

    /**
     * @var bool Show user reviews tab on User pages.
     */
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
