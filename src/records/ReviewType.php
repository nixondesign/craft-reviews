<?php

namespace rynpsc\reviews\records;

use rynpsc\reviews\db\Table;

use craft\db\ActiveRecord;

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
