<?php

namespace rynpsc\reviews\elements\db;

use Craft;
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
use yii\base\NotSupportedException;
use yii\db\Exception;

class ReviewQuery extends ElementQuery
{
    /**
     * @var mixed The minimum Submission Date that resulting reviews can have.
     */
    public mixed $after = null;

    /**
     * @var mixed The maximum Submission Date that resulting reviews can have.
     */
    public mixed $before = null;

    /**
     * @var mixed The subject element ID(s) that the resulting reviews must belong to.
     */
    public mixed $ownerId = null;

    /**
     * @var mixed The email address of the author the resulting reviews must belong to.
     */
    public mixed $email = null;

    /**
     * @var mixed The Moderation Status(es) the resulting reviews must have.
     */
    public mixed $moderationStatus = null;

    /**
     * @var mixed The rating the resulting reviews must have.
     */
    public mixed $rating = null;

    /**
     * @var mixed The review body that resulting reviews must have.
     */
    public mixed $review = null;

    /**
     * @var mixed The Submission Date that the resulting reviews must have.
     */
    public mixed $submissionDate = null;

    /**
     * @var mixed The type of subject element the resulting reviews must have.
     */
    public mixed $subjectElementType = null;

    /**
     * @var mixed The review type ID(s) that the resulting reviews must have.
     */
    public mixed $typeId = null;

    /**
     * @var mixed The user ID(s) that the resulting reviews authors must have.
     */
    public mixed $authorId = null;

    /**
     * @var mixed The user group ID(s) that the resulting reviews authors must be in.
     */
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

    /**
     * Narrows the query results to only reviews that were submitted on or after a certain date.
     *
     * @param mixed $value
     * @return ReviewQuery
     */
    public function after(mixed $value): self
    {
        $this->after = $value;
        return $this;
    }

    /**
     * Narrows the query results to only reviews that were submitted before a certain date.
     *
     * @param mixed $value
     * @return $this
     */
    public function before(mixed $value): self
    {
        $this->before = $value;
        return $this;
    }

    /**
     * Narrows the query based on the element being reviewed.
     *
     * @param $value
     * @return $this
     */
    public function owner($value): self
    {
        $this->ownerId = $this->normaliseModelOrHandleParam(
            $value,
            Element::class,
        );

        return $this;
    }

    /**
     * Narrows the query based on the ID of the element being reviewed.
     *
     * @param $value
     * @return $this
     */
    public function ownerId($value): self
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * Narrows the query based on the type of element the review is for.
     *
     * @param $value
     * @return $this
     */
    public function subjectElementType($value): self
    {
        $this->subjectElementType = $value;
        return $this;
    }

    /**
     * Narrows the query based on the authors email.
     *
     * @param string $value
     * @return $this
     */
    public function email(string $value): self
    {
        $this->email = $value;
        return $this;
    }

    /**
     * Narrows the query based on the moderation status.
     *
     * @param $value
     * @return $this
     */
    public function moderationStatus($value): self
    {
        $this->moderationStatus = $value;
        return $this;
    }

    /**
     * Narrows the query based on the rating.
     *
     * @param $value
     * @return $this
     */
    public function rating($value): self
    {
        $this->rating = $value;
        return $this;
    }

    /**
     * Narrows the query based on the submission date.
     *
     * @param $value
     * @return $this
     */
    public function submissionDate($value): self
    {
        $this->submissionDate = $value;
        return $this;
    }

    /**
     * Narrows the query based on the reviews review type.
     *
     * @param $value
     * @return $this
     */
    public function type($value): self
    {
        $this->typeId = $this->normaliseModelOrHandleParam(
            $value,
            ReviewType::class,
            Table::REVIEWTYPES,
        );

        return $this;
    }

    /**
     * Narrows the query results based on the Reviews’ review type, per the types’ IDs.
     *
     * @param $value
     * @return $this
     */
    public function typeId($value): self
    {
        $this->typeId = $value;
        return $this;
    }

    /**
     * Narrows the query based on the reviews author.
     *
     * @param $value
     * @return $this
     */
    public function user($value): self
    {
        $this->authorId = $this->normaliseModelOrHandleParam($value, User::class);
        return $this;
    }

    /**
     * Narrows the query based on the reviews author, per the ID.
     *
     * @param $value
     * @return $this
     */
    public function authorId($value): self
    {
        $this->authorId = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the user group the reviews author belongs to.
     *
     * @param $value
     * @return $this
     */
    public function authorGroup($value): self
    {
        $this->authorGroupId = $this->normaliseModelOrHandleParam(
            $value,
            UserGroup::class,
            CraftTable::USERGROUPS,
        );

        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
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
        if ($this->typeId === []) {
            return false;
        }

        $this->joinElementTable('reviews_reviews');

        $this->query->select([
            'reviews_reviews.submissionDate',
            'reviews_reviews.ownerId',
            'reviews_reviews.id',
            'reviews_reviews.rating',
            'reviews_reviews.review',
            'reviews_reviews.siteId',
            'reviews_reviews.typeId',
            'reviews_reviews.authorId',
            'reviews_reviews.moderationStatus',
        ]);

        if ($this->ownerId) {
            $this->subQuery->andWhere(Db::parseNumericParam('reviews_reviews.ownerId', $this->ownerId));
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
            $this->subQuery->innerJoin(['e' => CraftTable::ELEMENTS], '[[e.id]] = [[reviews_reviews.ownerId]]');
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

    /**
     * Normalises a param that can be a mixture of one or more IDs, models
     * or handles to an array of IDs.
     *
     * If the value is an instance of the model the value of models' id property
     * will be returned if it exists. Strings are assumed to be "handles" and the
     * "id" of respective row in the table will be returned. Numeric values are
     * returned unaltered.
     *
     * If the original param value began with `and`, `or`, or `not`, that will be preserved.
     *
     * @param mixed $value
     * @param string $model The className of the model to extract the IDs from.
     * @param string|null $table The name of table to extract the IDs from.
     * @return array
     * @throws NotSupportedException
     */
    private function normaliseModelOrHandleParam(mixed $value, string $model, string $table = null): array
    {
        $ids = [];
        $handles = [];

        $values = is_array($value) ? $value : [$value];

        $glue = Db::extractGlue($values);

        $modelHasIdProperty = property_exists($model, 'id');

        foreach ($values as $v) {
            if ($v instanceof $model && $modelHasIdProperty) {
                $ids[] = $v->id ?? null;
            } elseif (is_numeric($v)) {
                $ids[] = $v;
            } elseif (is_string($v)) {
                $handles[] = $v;
            }
        }

        if ($table) {
            $ids = ArrayHelper::merge($ids, $this->handlesToIds($handles, $table));
        }

        if ($glue !== null) {
            array_unshift($ids, $glue);
        }

        return $ids;
    }

    /**
     * Takes an array of handles and returns the matching IDs from the specified table.
     *
     * @param array $handles
     * @param string $table
     * @return array
     * @throws NotSupportedException
     */
    private function handlesToIds(array $handles, string $table): array
    {
        if (count($handles) === 0) {
            return [];
        }

        if (!Craft::$app->db->tableExists($table) || !Craft::$app->db->columnExists($table, 'handle')) {
            return [];
        }

        return (new Query())
            ->select('id')
            ->from($table)
            ->where(Db::parseParam('handle', $handles))
            ->column();
    }
}
