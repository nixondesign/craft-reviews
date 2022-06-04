<?php

namespace rynpsc\reviews\elements\db;

use craft\base\Element;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\UserGroup;
use DateTime;
use rynpsc\reviews\db\Table;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\models\ReviewType;
use rynpsc\reviews\models\Summary;
use rynpsc\reviews\Plugin;
use yii\base\InvalidConfigException;
use yii\db\Exception;

class ReviewQuery extends ElementQuery
{
    public mixed $after = null;
    public mixed $before = null;
    public mixed $elementId = null;
    public ?string $email = null;
    public mixed $moderationStatus = null;
    public mixed $rating = null;
    public mixed $review = null;
    public mixed $submissionDate = null;
    public mixed $subjectElementType = null;
    public mixed $typeId = null;
    public mixed $authorId = null;
    public mixed $authorGroupId = null;

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = 'live';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = [
        'reviews_reviews.submissionDate' => SORT_DESC,
    ];

    public function after($value): self
    {
        $this->after = $value;
        return $this;
    }

    public function before($value): self
    {
        $this->before = $value;
        return $this;
    }

    public function element($value): self
    {
        $this->elementId = $this->parseModelHandle(
            $value,
            Element::class,
            CraftTable::ELEMENTS,
        );

        return $this;
    }

    public function elementId($value): self
    {
        $this->elementId = $value;
        return $this;
    }

    public function subjectElementType($value): self
    {
        $this->subjectElementType = $value;
        return $this;
    }

    public function email($value): self
    {
        $this->email = $value;
        return $this;
    }

    public function moderationStatus($value): self
    {
        $this->moderationStatus = $value;
        return $this;
    }

    public function rating($value): self
    {
        $this->rating = $value;
        return $this;
    }

    public function submissionDate($value): self
    {
        $this->submissionDate = $value;
        return $this;
    }

    public function type($value): self
    {
        $this->typeId = $this->parseModelHandle(
            $value,
            ReviewType::class,
            Table::REVIEWTYPES,
        );

        return $this;
    }

    public function typeId($value): self
    {
        $this->typeId = $value;
        return $this;
    }

    public function user($value): self
    {
        $this->authorId = $this->parseModelHandle($value, User::class);
        return $this;
    }

    public function authorId($value): self
    {
        $this->authorId = $value;
        return $this;
    }

    public function authorGroup($value): self
    {
        $this->authorGroupId = $this->parseModelHandle(
            $value,
            UserGroup::class,
            CraftTable::USERGROUPS,
        );

        return $this;
    }

    public function authorGroupId($value): self
    {
        $this->authorId = $value;
        return $this;
    }

    /**
     * @throws InvalidConfigException|Exception
     */
    public function summary($db = null): ?Summary
    {
        $typesService = Plugin::getInstance()->getReviewTypes();

        if ($this->typeId === null) {
            throw new InvalidConfigException("Query is missing a valid 'type' or 'typeId' parameter.");
        }

        $type = $typesService->getTypeById($this->typeId);

        if ($type === null) {
            return null;
        }

        $this->select([
            'count' => 'COUNT(*)',
            'average' => 'AVG([[rating]])',
            'lowest' => 'MIN([[rating]])',
            'highest' => 'MAX([[rating]])',
        ]);

        for ($i = 1; $i <= $type->maxRating; $i++) {
            $param = ':rating' . $i;
            $this->addParams([$param => $i]);
            $this->addSelect(['countRating' . $i => "COUNT(CASE WHEN [[rating]] = {$param} THEN 1 END)"]);
        }

        $result = $this->createCommand($db)->queryOne();

        $model = new Summary();
        $model->setAttributes($result);

        foreach ($result as $key => $value) {
            if (StringHelper::startsWith($key, 'countRating')) {
                $model->ratingCounts[] = $value;
            }
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('reviews_reviews');

        $this->query->select([
            'reviews_reviews.submissionDate',
            'reviews_reviews.elementId',
            'reviews_reviews.id',
            'reviews_reviews.rating',
            'reviews_reviews.review',
            'reviews_reviews.siteId',
            'reviews_reviews.typeId',
            'reviews_reviews.authorId',
            'reviews_reviews.moderationStatus',
        ]);

        if ($this->elementId) {
            $this->subQuery->andWhere(Db::parseNumericParam('reviews_reviews.elementId', $this->elementId));
        }

        if ($this->email) {
            $this->subQuery->innerJoin(['u' => CraftTable::USERS], '[[u.id]] = [[reviews_reviews.authorId]]');
            $this->subQuery->andWhere(Db::parseParam('u.email', $this->email));
        }

        if ($this->moderationStatus) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.moderationStatus', $this->moderationStatus));
        }

        if ($this->submissionDate) {
            $this->subQuery->andWhere(Db::parseDateParam('reviews_reviews.submissionDate', $this->submissionDate));
        } else {
            if ($this->after) {
                $this->subQuery->andWhere(Db::parseDateParam('reviews_reviews.submissionDate', $this->after, '>='));
            }

            if ($this->before) {
                $this->subQuery->andWhere(Db::parseDateParam('reviews_reviews.submissionDate', $this->before, '<'));
            }
        }

        if ($this->subjectElementType) {
            $this->subQuery->innerJoin(['e' => CraftTable::ELEMENTS], '[[e.id]] = [[reviews_reviews.elementId]]');
            $this->subQuery->andWhere(Db::parseParam('e.type', $this->subjectElementType));
        }

        if ($this->rating) {
            $this->subQuery->andWhere(Db::parseNumericParam('reviews_reviews.rating', $this->rating));
        }

        if ($this->review) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.review', $this->review));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseNumericParam('reviews_reviews.typeId', $this->typeId));
        }

        if ($this->authorId) {
            $this->subQuery->andWhere(Db::parseNumericParam('reviews_reviews.authorId', $this->authorId));
        }

        if ($this->authorGroupId) {
            $this->subQuery->innerJoin(['usergroups_users' => CraftTable::USERGROUPS_USERS], '[[usergroups_users.userId]] = [[reviews_reviews.authorId]]');
            $this->subQuery->andWhere(Db::parseNumericParam('usergroups_users.groupId', $this->authorGroupId));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status): mixed
    {
        $currentTimeDb = Db::prepareDateForDb(new DateTime());

        return match ($status) {
            Review::STATUS_LIVE => [
                'elements.enabled' => true,
                'reviews_reviews.moderationStatus' => Review::STATUS_APPROVED,
                'reviews_reviews.submissionDate' => ['<=', $currentTimeDb],
            ],
            Review::STATUS_APPROVED => [
                'reviews_reviews.moderationStatus' => Review::STATUS_APPROVED,
            ],
            Review::STATUS_PENDING => [
                'reviews_reviews.moderationStatus' => Review::STATUS_PENDING,
            ],
            Review::STATUS_REJECTED => [
                'reviews_reviews.moderationStatus' => Review::STATUS_REJECTED,
            ],
            default => parent::statusCondition($status),
        };
    }

    protected function parseModelHandle($value, string $model, string $table = null): array
    {
        $ids = [];
        $handles = [];

        $values = ArrayHelper::isTraversable($value) ? $value : [$value];

        if ($glue = Db::extractGlue($values)) {
            array_unshift($ids, $glue);
        }

        foreach ($values as $v) {
            if ($v instanceof $model) {
                $ids[] = $v->id ?? null;
            } elseif (is_numeric($v)) {
                $ids[] = $v;
            } elseif (is_string($v)) {
                $handles[] = $v;
            }
        }

        if ($table && count($handles)) {
            $ids = ArrayHelper::merge($ids, (new Query())
                ->select('id')
                ->from($table)
                ->where(Db::parseParam('handle', $handles))
                ->column());
        }

        return $ids;
    }
}
