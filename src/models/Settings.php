<?php

namespace rynpsc\reviews\models;

use craft\base\Model;

class Settings extends Model
{
    public const RATING_DISPLAY_STAR = 'star';
    public const RATING_DISPLAY_NUMERIC = 'numeric';

    /**
     * @var bool Show sidebar badge.
     */
    public bool $showSidebarBadge = true;

    /**
     * @var string The name of the honeypot field.
     */
    public string $honeypotFieldName = 'user-name';

    /**
     * @var string How ratings should be displayed in element indexes.
     */
    public string $elementIndexRatingDisplayType = 'stars';

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
        $rules[] = ['elementIndexRatingDisplayType', 'in', 'range' => [
            self::RATING_DISPLAY_STAR,
            self::RATING_DISPLAY_NUMERIC,
        ]];

        return $rules;
    }
}
