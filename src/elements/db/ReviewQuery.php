<?php

namespace rynpsc\reviews\elements\db;

use rynpsc\reviews\Plugin;
use rynpsc\reviews\db\Table;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\models\ReviewType;
use rynpsc\reviews\models\Summary;

use DateTime;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use yii\base\InvalidConfigException;

class ReviewQuery extends ElementQuery
{
    public $after;
    public $before;
    public $element;
    public $elementId;
    public $email;
    public $fullName;
    public $moderationStatus;
    public $rating;
    public $review;
    public $submissionDate;
    public $type;
    public $typeId;
    public $userId;

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
    protected $defaultOrderBy = [
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
        $this->elementId = $value->id;
        return $this;
    }

    public function elementId($value): self
    {
        $this->elementId = $value;
        return $this;
    }

    public function email($value): self
    {
        $this->email = $value;
        return $this;
    }

    public function fullName($value): self
    {
        $this->fullName = $value;
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
        if ($value instanceof ReviewType) {
            $this->typeId = $value->id;
        } elseif ($value !== null) {
            $this->typeId = (new Query())
                ->select(['id'])
                ->from([Table::REVIEWTYPES])
                ->where(Db::parseParam('handle', $value))
                ->scalar();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    public function typeId($value): self
    {
        $this->typeId = $value;
        return $this;
    }

    public function userId($value): self
    {
        $this->userId = $value;
        return $this;
    }

    /**
     * @throws InvalidConfigException
     */
    public function summary($db = null): ?Summary
    {
        $typesService = Plugin::getInstance()->getReviewTypes();

        if ($this->typeId === null) {
            throw new InvalidConfigException("Query is missing a valid 'type' or 'typeId' parameter.");
        }

        $type = $typesService->getReviewTypeById($this->typeId);

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
            'reviews_reviews.email',
            'reviews_reviews.id',
            'reviews_reviews.fullName',
            'reviews_reviews.rating',
            'reviews_reviews.review',
            'reviews_reviews.siteId',
            'reviews_reviews.typeId',
            'reviews_reviews.userId',
            'reviews_reviews.moderationStatus',
        ]);

        if ($this->elementId) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.elementId', $this->elementId));
        }

        if ($this->email) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.email', $this->email));
        }

        if ($this->moderationStatus) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.moderationStatus', $this->moderationStatus));
        }

        if ($this->fullName) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.fullName', $this->fullName));
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

        if ($this->rating) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.rating', $this->rating));
        }

        if ($this->review) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.review', $this->review));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.typeId', $this->typeId));
        }

        if ($this->userId) {
            $this->subQuery->andWhere(Db::parseParam('reviews_reviews.userId', $this->userId));
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
}
