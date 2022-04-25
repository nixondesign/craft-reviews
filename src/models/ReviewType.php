<?php

namespace rynpsc\reviews\models;

use rynpsc\reviews\elements\Review;
use rynpsc\reviews\records\ReviewType as ReviewTypeRecord;

use Craft;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;

class ReviewType extends Model
{
    public ?int $id = null;

    public ?string $name = null;

    public ?string $handle = null;

    public ?int $fieldLayoutId = null;

    public bool $allowGuestReviews = true;

    public bool $requireGuestEmail = true;

    public bool $requireGuestName = true;

    public string $defaultStatus = Review::STATUS_PENDING;

    public ?string $uid = null;

    public int $maxRating = 5;

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
        $rules[] = [['requireGuestEmail'], 'boolean'];
        $rules[] = [['requireGuestName'], 'boolean'];

        $rules[] = [['handle'], HandleValidator::class];

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['name', 'handle'], 'string', 'max' => 255];
        $rules[] = [['name', 'handle'], UniqueValidator::class, 'targetClass' => ReviewTypeRecord::class];

        return $rules;
    }
}
