<?php

namespace rynpsc\reviews\models;

use Craft;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use DateTime;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\records\ReviewType as ReviewTypeRecord;

/**
 * @property-read array $config
 */
class ReviewType extends Model
{
    public ?int $id = null;

    public ?string $name = null;

    public ?string $handle = null;

    public ?int $fieldLayoutId = null;

    public bool $allowGuestReviews = true;

    public bool $requireFullName = true;

    public string $defaultStatus = Review::STATUS_PENDING;

    public ?string $uid = null;

    public bool $hasTitleField = true;

    public ?string $titleFormat = null;

    public int $maxRating = 5;

    public ?DateTime $dateCreated = null;

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
