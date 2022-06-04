<?php

namespace rynpsc\reviews\records;

use craft\db\ActiveRecord;

use craft\elements\User;
use rynpsc\reviews\db\Table;
use yii\db\ActiveQueryInterface;

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
