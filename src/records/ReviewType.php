<?php

namespace rynpsc\reviews\records;

use craft\db\ActiveRecord;

use rynpsc\reviews\db\Table;

class ReviewType extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return Table::REVIEWTYPES;
    }
}
