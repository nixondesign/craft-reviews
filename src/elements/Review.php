<?php

namespace rynpsc\reviews\elements;

use rynpsc\reviews\Plugin;
use rynpsc\reviews\db\Table;
use rynpsc\reviews\elements\db\ReviewQuery;
use rynpsc\reviews\enums\Permissions;
use rynpsc\reviews\models\ReviewType;
use rynpsc\reviews\records\Review as ReviewRecord;

use Craft;
use DateTime;
use Exception;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\User;
use craft\elements\actions\CopyReferenceTag;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\App;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use yii\base\InvalidConfigException;

class Review extends Element
{
    public const STATUS_LIVE = 'live';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public $submissionDate;
    public $elementId;
    public $rating;
    public $review;
    public $siteId;
    public $typeId;
    public $userId;
    public $moderationStatus;
    private $_email;
    private $_fullName;
    private $_user;
    private $_guest;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('reviews', 'Review');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('reviews', 'review');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('reviews', 'Reviews');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('reviews', 'reviews');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): string
    {
        return 'review';
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => [
                'color' => 'green',
                'label' => Craft::t('reviews', 'Live'),
            ],
            self::STATUS_APPROVED => [
                'color' => 'green',
                'label' => Craft::t('reviews', 'Approved'),
            ],
            self::STATUS_PENDING => [
                'color' => 'orange',
                'label' => Craft::t('reviews', 'Pending'),
            ],
            self::STATUS_REJECTED => [
                'color' => 'red',
                'label' => Craft::t('reviews', 'Rejected'),
            ],
            self::STATUS_DISABLED => [
                'color' => '',
                'label' => Craft::t('reviews', 'Disabled'),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function find(): ElementQueryInterface
    {
        return new ReviewQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            '*' => [
                'key' => '*',
                'label' => 'All Reviews',
                'criteria' => [],
                'defaultSort' => ['submissionDate', 'desc'],
            ],
        ];

        $sources[] = ['heading' => Craft::t('reviews', 'Status')];

        foreach (self::moderationStatuses() as $key => $status) {
            $sources[] = [
                'key' => $key,
                'status' => $status['color'],
                'label' => $status['label'],
                'criteria' => ['moderationStatus' => $key],
                'defaultSort' => ['submissionDate', 'desc'],
            ];
        }

        if (!Plugin::getInstance()->getSettings()->showRatingElementSources) {
            return $sources;
        }

        $sources[] = ['heading' => Craft::t('reviews', 'Rating')];

        for ($i = 1; $i <= self::getHighestReviewTypeRating(); $i++) {
            $criteria = [
                'rating' => $i,
                'moderationStatus' => null,
            ];

            $sources[] = [
                'key' => 'rating:' . $i,
                'label' => $i . ' Star',
                'criteria' => $criteria,
                'defaultSort' => ['submissionDate', 'desc'],
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'review' => Craft::t('reviews', 'Review'),
            'submissionDate' => Craft::t('reviews', 'Date'),
            'elementId' => Craft::t('reviews', 'Element'),
            'email' => Craft::t('reviews', 'Email'),
            'fullName' => Craft::t('reviews', 'Name'),
            'rating' => Craft::t('reviews', 'Rating'),
            'user' => Craft::t('reviews', 'User'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'review',
            'submissionDate',
            'rating',
            'elementId',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];
        $elements = Craft::$app->getElements();

        $actions[] = $elements->createAction([
            'type' => CopyReferenceTag::class,
            'elementType' => static::class,
        ]);

        $actions[] = $elements->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('reviews', 'Are you sure you want to delete the selected reviews?'),
            'successMessage' => Craft::t('reviews', 'Reviews deleted.'),
        ]);

        return $actions;
    }

    public static function moderationStatuses(): array
    {
        return [
            self::STATUS_APPROVED => [
                'color' => 'green',
                'label' => Craft::t('reviews', 'Approved'),
            ],
            self::STATUS_PENDING => [
                'color' => 'orange',
                'label' => Craft::t('reviews', 'Pending'),
            ],
            self::STATUS_REJECTED => [
                'color' => 'red',
                'label' => Craft::t('reviews', 'Rejected'),
            ],
        ];
    }

    public static function getReviewElementTitleHtml(&$context)
    {
        if (!isset($context['element'])) {
            return null;
        }

        if (get_class($context['element']) === static::class) {
            return Craft::$app->getView()->renderTemplate('reviews/elements/table-attributes/title', [
                'review' => $context['element'],
            ]);
        }
    }

    public static function getHighestReviewTypeRating()
    {
        return (new Query())
            ->select('maxRating')
            ->from(Table::REVIEWTYPES)
            ->orderBy(['[[maxRating]]' => SORT_DESC])
            ->limit(1)
            ->scalar();
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->typeId = (int)$this->typeId;
        $this->userId = (int)$this->userId;
        $this->elementId = (int)$this->elementId;
        $this->rating = (int)$this->rating;

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'submissionDate';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): string
    {
        $url = UrlHelper::cpUrl('reviews/' . $this->id);

        if (Craft::$app->getIsMultiSite()) {
            $url .= '?site=' . $this->getSite()->handle;
        }

        return $url;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {

        return [$this->siteId ?: Craft::$app->getSites()->getPrimarySite()->id];
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        if ($this->scenario === self::SCENARIO_LIVE && !$this->submissionDate) {
            $this->submissionDate = new DateTime();
            $timestamp = $this->submissionDate->getTimestamp();
            $this->submissionDate->setTimestamp($timestamp - $timestamp % 60);
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = ReviewRecord::findOne([$this->id]);

            if (!$record) {
                throw new Exception('Invalid record ID: ' . $this->id);
            }
        } else {
            $record = new ReviewRecord();
            $record->id = (int)$this->id;

            $this->moderationStatus = $this->getType()->defaultStatus;
        }

        if ($this->userId) {
            $record->userId = $this->userId;
        } else if ($this->email) {
            $user = User::find()->email($this->email)->one();

            if ($user) {
                $record->userId = $user->id;
            } else {
                $record->email = $this->email;
                $record->fullName = $this->_fullName;
            }
        }

        $this->siteId = Craft::$app
            ->getElements()
            ->getElementById($this->elementId)
            ->siteId;

        $record->elementId = $this->elementId;
        $record->moderationStatus = $this->moderationStatus;
        $record->submissionDate = $this->submissionDate;
        $record->rating = $this->rating;
        $record->review = $this->review;
        $record->siteId = $this->siteId;
        $record->typeId = $this->typeId;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): string
    {
        $status = parent::getStatus();

        if ($this->moderationStatus === self::STATUS_REJECTED) {
            return self::STATUS_REJECTED;
        }

        if ($this->moderationStatus === self::STATUS_PENDING) {
            return self::STATUS_PENDING;
        }

        if ($this->enabled && $this->moderationStatus === self::STATUS_APPROVED) {
            if ($this->submissionDate === null) {
                return self::STATUS_LIVE;
            }

            $postTimeStamp = $this->submissionDate->getTimestamp();
            $currentTimeStamp = DateTimeHelper::currentTimeStamp();

            if ($postTimeStamp > $currentTimeStamp) {
                return self::STATUS_PENDING;
            }

            return self::STATUS_LIVE;
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return $this->getType()->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function getIsEditable(): bool
    {
        $type = $this->getType();
        $user = Craft::$app->getUser();

        if (!$user->checkPermission(Permissions::VIEW_REVIEWS . ':' . $type->uid)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getIsDeletable(): bool
    {
        $type = $this->getType();
        $user = Craft::$app->getUser();

        if (!$user->checkPermission(Permissions::DELETE_REVIEWS . ':' . $type->uid)) {
            return false;
        }

        return true;
    }

    public function getElement(): ?ElementInterface
    {
        return Craft::$app->getElements()->getElementById(
            $this->elementId,
            null,
            $this->siteId,
            [
                'trashed' => null,
            ]
        );
    }

    public function getElementDisplayName(): string
    {
        $element = $this->getElement();

        if ($element === null) {
            return '';
        }

        $class = get_class($element);

        return $class::displayName();
    }

    public function isGuest(): bool
    {
        return $this->getUser() === null;
    }

    public function getEmail(): ?string
    {
        $user = $this->getUser();

        if ($user === null) {
            return $this->_email;
        }

        return $user->email;
    }

    public function setEmail($value): void
    {
        $this->_email = $value;
    }

    public function getFullName(): ?string
    {
        $user = $this->getUser();

        if ($user === null) {
            return $this->_fullName;
        }

        return $user->getFriendlyName();
    }

    public function setFullName($value): void
    {
        $this->_fullName = $value;
    }

    public function getUser(): ?User
    {
        if ($this->_user) {
            return $this->_user;
        }

        if ($this->_guest === true) {
            return null;
        }

        if ($this->userId === null) {
            $this->_guest = true;
            return null;
        }

        if (($this->_user = Craft::$app->getUsers()->getUserById($this->userId)) === null) {
            $this->_user = null;
            $this->_guest = true;
        }

        return $this->_user;
    }

    public function getType(): ReviewType
    {
        if ($this->typeId === null) {
            throw new InvalidConfigException('Review is missing its review type ID');
        }

        $reviewType = Plugin::getInstance()->getReviewTypes()->getReviewTypeById($this->typeId);

        if ($reviewType === null) {
            throw new InvalidConfigException('Invalid review type ID: ' . $this->typeId);
        }

        return $reviewType;
    }

    public function elementValidator($attribute, $params, $validator)
    {
        if ($this->id !== null) {
            return;
        }

        $element = Craft::$app->getElements()->getElementById($this->$attribute);

        if ($element === null) {
            $validator->addError($this, $attribute, 'Invalid attribute "{attribute}", no element with the ID {value} exists.');
        }
    }

    public function uniqueUserValidator($attribute): void
    {
        $query = self::find()
            ->status(null)
            ->elementId($this->elementId);

        if ($this->userId) {
            $query->userId($this->userId);
        } else {
            $query->email($this->getEmail());
        }

        $review = $query->one();

        if ($review === null || $review->id === $this->id) {
            return;
        }

        $this->addError($attribute, Craft::t('reviews', 'Review already submitted for this {element}, you cannot leave another.', [
            'element' => $this->getElementDisplayName(),
        ]));
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = ['review', 'required'];
        $rules[] = [['typeId'], 'number', 'integerOnly' => true];
        $rules[] = [['email'], 'email', 'enableIDN' => App::supportsIdn(), 'enableLocalIDN' => false];
        $rules[] = [['rating'], 'required'];

        $rules[] = ['elementId', 'elementValidator'];
        $rules[] = [['rating'], 'uniqueUserValidator'];

        $rules[] = [['email'], 'required', 'when' => function () {
            return $this->getType()->requireGuestEmail;
        }];

        $rules[] = [['fullName'], 'required', 'when' => function () {
            return $this->getType()->requireGuestName;
        }];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'rating':
                return Craft::$app->getView()->renderTemplate('reviews/elements/table-attributes/rating', [
                    'review' => $this,
                ]);

            case 'elementId':
                if (!$element = $this->getElement()) {
                    return Craft::t('reviews', 'Deleted');
                }

                return Cp::elementHtml($element);

            case 'user':
                if (!$user = $this->getUser()) {
                    return Craft::t('reviews', 'Guest');
                }

                return Cp::elementHtml($user);

            default:
        }

        return parent::tableAttributeHtml($attribute);
    }
}
