<?php

namespace rynpsc\reviews\records;

use rynpsc\reviews\db\Table;

use craft\elements\User;
use craft\db\ActiveRecord;
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
