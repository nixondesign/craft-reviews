<?php

namespace nixondesign\reviews\models;

use Craft;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use DateTime;
use nixondesign\reviews\elements\Review;
use nixondesign\reviews\records\ReviewType as ReviewTypeRecord;

/**
 * @property-read string $cpEditUrl
 * @property-read array $config
 */
class ReviewType extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var int|null Field layout ID
     */
    public ?int $fieldLayoutId = null;

    /**
     * @var bool Allow non-logged in users to submit reviews.
     */
    public bool $allowGuestReviews = true;

    /**
     * @var bool Require name for guest reviews.
     */
    public bool $requireFullName = true;

    /**
     * @var string The status applied to new reviews.
     */
    public string $defaultStatus = Review::STATUS_PENDING;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

    /**
     * @var bool Has title field.
     */
    public bool $hasTitleField = true;

    /**
     * @var string|null The format of automatically generated title.
     */
    public ?string $titleFormat = null;

    /**
     * @var int The maximum rating reviews of this type are allowed to have.
     */
    public int $maxRating = 5;

    /**
     * @var DateTime|null Date created
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null Date updated
     */
    public ?DateTime $dateUpdated = null;

    /**
     * Use the translated review type's name as the string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return Craft::t('reviews', $this->name) ?: static::class;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'handle' => Craft::t('reviews', 'Handle'),
            'name' => Craft::t('reviews', 'Name'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['fieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => Review::class,
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['id', 'fieldLayoutId', 'maxRating'], 'number', 'integerOnly' => true];

        $rules[] = [['allowGuestReviews'], 'boolean'];
        $rules[] = [['requireFullName'], 'boolean'];

        $rules[] = [['handle'], HandleValidator::class];

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['name', 'handle'], 'string', 'max' => 255];
        $rules[] = [['name', 'handle'], UniqueValidator::class, 'targetClass' => ReviewTypeRecord::class];

        $rules[] = [['titleFormat'], 'required', 'when' => function() {
            return $this->hasTitleField === false;
        }];

        return $rules;
    }

    /**
     * Returns the edit URL in the control panel.
     *
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('reviews/settings/types/' . $this->id);
    }

    /**
     * Returns the review type’s project config.
     *
     * @return array
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'handle' => $this->handle,
            'maxRating' => $this->maxRating,
            'allowGuestReviews' => $this->allowGuestReviews,
            'requireFullName' => $this->requireFullName,
            'defaultStatus' => $this->defaultStatus,
            'hasTitleField' => $this->hasTitleField,
            'titleFormat' => $this->titleFormat,
        ];

        $fieldLayout = $this->getFieldLayout();

        if ($fieldLayoutConfig = $fieldLayout->getConfig()) {
            $config['fieldLayouts'] = [
                $fieldLayout->uid => $fieldLayoutConfig,
            ];
        }

        return $config;
    }

    /**
     * Gets the name of a given Permission suffixed with the UID.
     *
     * @param $prefix
     * @return string
     */
    public function getPermissionKey($prefix): string
    {
        return "$prefix:$this->uid";
    }
}
