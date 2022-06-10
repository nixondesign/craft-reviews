<?php

namespace rynpsc\reviews\records;

use craft\db\ActiveRecord;
use craft\elements\User;
use DateTime;
use rynpsc\reviews\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id ID
 * @property int $ownerId Element ID
 * @property int|null $authorId Author ID
 * @property int $siteId Site ID
 * @property int $typeId Type ID
 * @property string $moderationStatus Moderation status
 * @property string $review Review
 * @property int $rating Rating
 * @property DateTime|null $submissionDate Submission date
 * @property-read ActiveQueryInterface $user
 */
class Review extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::REVIEWS;
    }

    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
