<?php

namespace rynpsc\reviews\elements;

use Craft;
use craft\helpers\ArrayHelper;
use DateTime;
use RuntimeException;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\User;
use craft\elements\actions\CopyReferenceTag;
use craft\elements\actions\Delete;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\App;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\DateTimeValidator;
use craft\web\CpScreenResponseBehavior;
use rynpsc\reviews\Plugin;
use rynpsc\reviews\db\Table;
use rynpsc\reviews\elements\conditions\ReviewCondition;
use rynpsc\reviews\elements\db\ReviewQuery;
use rynpsc\reviews\enums\Permissions;
use rynpsc\reviews\models\ReviewType;
use rynpsc\reviews\records\Review as ReviewRecord;
use rynpsc\reviews\web\assets\ReviewsAsset;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * @property null|string|array|int $authorId
 * @property-read string $elementDisplayName
 * @property-read bool $isGuest
 * @property-read ReviewType $type
 * @property-read array $dirtyAttributes
 * @property-read null|string $postEditUrl
 * @property-read string[] $cacheTags
 * @property-read null|ElementInterface $element
 */
class Review extends Element
{
    public const STATUS_LIVE = 'live';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public ?DateTime $submissionDate = null;
    public ?int $elementId = null;
    public ?int $rating = null;
    public ?int $typeId = null;
    public ?string $moderationStatus = null;
    public ?string $review = null;

    protected ?User $author = null;
    protected ?int $_authorId = null;
    protected ?string $email = null;
    protected ?string $fullName = null;

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
        return true;
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
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ReviewCondition::class, [static::class]);
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
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle === 'author') {
            $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

            $map = (new Query())
                ->select(['id as source', 'authorId as target'])
                ->from(Table::REVIEWS)
                ->where(['and', ['id' => $sourceElementIds], ['not', ['authorId' => null]]])
                ->all();

            return [
                'map' => $map,
                'elementType' => User::class,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
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
            'author' => Craft::t('reviews', 'Author'),
            'elementId' => Craft::t('reviews', 'Element'),
            'rating' => Craft::t('reviews', 'Rating'),
            'submissionDate' => Craft::t('reviews', 'Date'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'rating',
            'elementId',
            'authorId',
            'submissionDate',
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
    protected function cpEditUrl(): ?string
    {
        $type = $this->getType();

        $path = sprintf('reviews/%s/%s', $type->handle, $this->getCanonicalId());

        if ($this->slug && !str_starts_with($this->slug, '__')) {
            $path .= "-$this->slug";
        }

        return UrlHelper::cpUrl($path);
    }

    /**
     * @inheritdoc
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('reviews');
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

        $this->moderationStatus ??= $this->getType()->defaultStatus;

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        $this->updateTitle();

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = ReviewRecord::findOne([$this->id]);

            if (!$record) {
                throw new RuntimeException('Invalid record ID: ' . $this->id);
            }
        } else {
            $record = new ReviewRecord();
            $record->id = (int)$this->id;
            $record->elementId = $this->elementId;
            $record->typeId = $this->typeId;
            $record->siteId ??= Craft::$app->getSites()->getCurrentSite()->id;
        }

        if ($this->getEmail()) {
            $user = $this->ensureUser();
        } else {
            $user = null;
        }

        $record->rating = $this->rating;
        $record->review = $this->review;
        $record->authorId = $user?->id ?? null;
        $record->submissionDate = $this->submissionDate;
        $record->moderationStatus = $this->moderationStatus;

        $dirtyAttributes = array_keys($record->getDirtyAttributes());

        $record->save(false);

        $this->setDirtyAttributes($dirtyAttributes);

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
    public function getFieldLayout(): FieldLayout|null
    {
        return $this->getType()->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        $type = $this->getType();

        if ($this->_authorId !== $user->id) {
            $user->can($type->getPermissionKey(Permissions::VIEW_PEER_REVIEWS));
        }

        return $user->can($type->getPermissionKey(Permissions::VIEW_REVIEWS));
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }

        $type = $this->getType();

        if ($this->_authorId !== $user->id) {
            $user->can($type->getPermissionKey(Permissions::SAVE_PEER_REVIEWS));
        }

        return $user->can($type->getPermissionKey(Permissions::SAVE_REVIEWS));
    }

    /**
     * @inheritdoc
     */
    public function canDuplicate(User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canCreateDrafts(User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        if (parent::canDelete($user)) {
            return true;
        }

        $type = $this->getType();

        if ($this->_authorId !== $user->id) {
            $user->can($type->getPermissionKey(Permissions::DELETE_PEER_REVIEWS));
        }

        return $user->can($type->getPermissionKey(Permissions::DELETE_REVIEWS));
    }

    /**
     * @inerhitdoc
     */
    public function metadata(): array
    {
        return [
            Craft::t('reviews', $this->getElementDisplayName()) => function() {
                return $this->element;
            },

            Craft::t('reviews', 'Author') => function() {
                if ($this->_authorId === null) {
                    return '';
                }

                $author = $this->getAuthor();

                if ($author === null) {
                    return Craft::t('reviews', 'Deleted User');
                }

                return $author;
            }
        ];
    }

    /**
     * @inerhitdoc
     */
    public function getCacheTags(): array
    {
        return [
            "reviewType:$this->typeId",
        ];
    }

    /**
     * @inerhitdoc
     */
    public function prepareEditScreen(Response $response, string $containerId): void
    {
        Craft::$app->getView()->registerAssetBundle(ReviewsAsset::class);

        $crumbs = [
            [
                'url' => UrlHelper::cpUrl('reviews'),
                'label' => Craft::t('reviews', 'Reviews'),
            ],
        ];

        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs($crumbs);
        $response->selectedSubnavItem = 'reviews';
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

    public function setAuthorId(array|int|string|null $_authorId): void
    {
        if ($_authorId === '') {
            $_authorId = null;
        }

        if (is_array($_authorId)) {
            $this->_authorId = reset($_authorId) ?: null;
        } else {
            $this->_authorId = $_authorId;
        }

        $this->author = null;
        $this->email = null;
        $this->fullName = null;
    }

    public function getAuthorId(): ?int
    {
        return $this->_authorId;
    }

    public function setEmail($value): void
    {
        $this->email = $value;
    }

    public function setFullName($value): void
    {
        $this->fullName = $value;
    }

    public function getEmail(): ?string
    {
        return $this->getAuthor()?->email ?? $this->email;
    }

    public function getFullName(): ?string
    {
        return $this->getAuthor()?->fullName ?? $this->fullName;
    }

    public function getIsGuest(): bool
    {
        return $this->getAuthor()?->id === null;
    }

    public function getAuthor(): ?User
    {
        if ($this->author) {
            return $this->author;
        }

        if ($this->_authorId === null) {
            return null;
        }

        return $this->author = Craft::$app->getUsers()->getUserById($this->_authorId);
    }

    public function getType(): ReviewType
    {
        if ($this->typeId === null) {
            throw new InvalidConfigException('Review is missing its review type ID');
        }

        $reviewType = Plugin::getInstance()->getReviewTypes()->getTypeById($this->typeId);

        if ($reviewType === null) {
            throw new InvalidConfigException('Invalid review type ID: ' . $this->typeId);
        }

        return $reviewType;
    }

    public function ensureUser(): User
    {
        $query = User::find()->status(null);

        $user = $query->email($this->getEmail())->one();

        if ($user) {
            return $user;
        }

        $user = new User();
        $user->email = $this->getEmail();
        $user->fullName = $this->getFullName();

        Craft::$app->getElements()->saveElement($user, false);

        return $user;
    }

    public function updateTitle(): void
    {
        $reviewType = $this->getType();

        if ($reviewType->hasTitleField) {
            return;
        }

        $language = Craft::$app->language;

        Craft::$app->getLocale();
        Craft::$app->language = $this->getSite()->language;

        $title = Craft::$app->getView()->renderObjectTemplate($reviewType->titleFormat, $this);

        if ($title !== '') {
            $this->title = $title;
        }

        Craft::$app->language = $language;
    }

    /**
     * @param $attribute
     * @param $params
     * @param $validator
     * @return void
     */
    public function elementValidator($attribute, $params, $validator): void
    {
        if ($this->id !== null) {
            return;
        }

        $element = Craft::$app->getElements()->getElementById($this->$attribute);

        if ($element === null) {
            $validator->addError($this, $attribute, 'Invalid attribute "{attribute}", no element with the ID {value} exists.');
        }
    }

    /**
     * @param $attribute
     * @return void
     */
    public function uniqueUserValidator($attribute): void
    {
        $email = $this->getEmail();

        if ($email === null) {
            return;
        }

        $query = self::find()
            ->status(null)
            ->email($email)
            ->drafts(null)
            ->draftOf(false)
            ->id(['not', $this->getCanonicalId()])
            ->elementId($this->elementId);

        /** @var Review|null $review */
        $review = $query->one();

        if ($review === null) {
            return;
        }

        $this->addError($attribute, Craft::t('reviews', 'Review already submitted for this {element}.', [
            'element' => $this->getElementDisplayName(),
        ]));
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $maxRating = $this->getType()->maxRating;

        $rules = parent::defineRules();

        $scenarios = [self::SCENARIO_DEFAULT, self::SCENARIO_LIVE];

        $rules[] = [['review'], 'required', 'on' => $scenarios];
        $rules[] = [['rating'], 'required', 'on' => $scenarios];
        $rules[] = [['rating'], 'number', 'min' => 1, 'max' => $maxRating, 'on' => $scenarios];
        $rules[] = [['typeId'], 'number', 'integerOnly' => true, 'on' => $scenarios];
        $rules[] = [['email'], 'email', 'enableIDN' => App::supportsIdn(), 'enableLocalIDN' => false];
        $rules[] = [['submissionDate'], DateTimeValidator::class];
        $rules[] = [['moderationStatus'], 'required'];
        $rules[] = [['moderationStatus'], 'in', 'range' => array_keys(self::moderationStatuses())];

        $rules[] = [['elementId'], 'elementValidator'];
        $rules[] = [['email', 'authorId'], 'uniqueUserValidator', 'on' => $scenarios];
        $rules[] = [['authorId', 'typeId'], 'number', 'integerOnly' => true, 'on' => $scenarios];

        $isCp = Craft::$app->getRequest()->isCpRequest;

        $rules[] = [['email'], 'required', 'when' => function () use ($isCp) {
            return !$isCp && $this->getIsGuest();
        }];

        $rules[] = [['fullName'], 'required', 'when' => function () use ($isCp) {
            return !$isCp && $this->getType()->requireFullName && $this->getIsGuest();
        }];

        return $rules;
    }

    /**
     * @inerhitdoc
     */
    protected function metaFieldsHtml(bool $static): string
    {
        $fields = [];

        $fields[] = Cp::selectFieldHtml([
            'label' => Craft::t('reviews', 'Status'),
            'id' => 'moderationStatus',
            'name' => 'moderationStatus',
            'value' => $this->moderationStatus,
            'errors' => $this->getErrors('moderationStatus'),
            'disabled' => $static,
            'options' => self::moderationStatuses(),
        ]);

        $fields[] = Cp::dateTimeFieldHtml([
            'label' => Craft::t('reviews', 'Date'),
            'id' => 'submissionDate',
            'name' => 'submissionDate',
            'value' => $this->submissionDate,
            'errors' => $this->getErrors('submissionDate'),
            'disabled' => $static,
        ]);

        $author = $this->getAuthor();

        if ($author->id === null) {
            $author = null;
        }

        $fields[] = Cp::elementSelectFieldHtml([
            'label' => Craft::t('reviews', 'Author'),
            'id' => 'authorId',
            'name' => 'authorId',
            'elementType' => User::class,
            'selectionLabel' => Craft::t('app', 'Choose'),
            'limit' => 1,
            'errors' => $this->getErrors('authorId'),
            'elements' => [$author],
            'disabled' => $static,
        ]);

        $fields[] = parent::metaFieldsHtml($static);

        return implode("\n", $fields);
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        Craft::$app->getView()->registerAssetBundle(ReviewsAsset::class);

        switch ($attribute) {
            case 'rating':
                return Craft::$app->getView()->renderTemplate('reviews/elements/table-attributes/rating', [
                    'review' => $this,
                ]);
            case 'elementId':
                if (!$element = $this->getElement()) {
                    return Craft::t('reviews', 'Deleted Element');
                }

                return Cp::elementHtml($element);
            case 'author':
                if ($this->_authorId === null) {
                    return '';
                }

                $author = $this->getAuthor();

                if ($author === null) {
                    return Craft::t('reviews', 'Deleted User');
                }

                return Cp::elementHtml($author);
            default:
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    protected function uiLabel(): ?string
    {
        if (!isset($this->title) || trim($this->title) === '') {
            return Craft::t('reviews', 'Untitled review');
        }

        return null;
    }
}
