<?php

namespace rynpsc\reviews\records;

use craft\db\ActiveRecord;
use rynpsc\reviews\db\Table;

/**
 * @property int $id ID
 * @property string $name Name
 * @property string $handle Handle
 * @property int $maxRating Maximum rating
 * @property string $defaultStatus Default status
 * @property bool $allowGuestReviews Allow guest reviews
 * @property bool $requireFullName Require full name
 * @property int|null $fieldLayoutId Field layout ID
 * @property bool $hasTitleField Has title field
 * @property string|null $titleFormat Title format
 */
class ReviewType extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::REVIEWTYPES;
    }
}
