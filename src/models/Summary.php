<?php

namespace rynpsc\reviews\models;

use craft\base\Model;
use yii\helpers\ArrayHelper;

class Summary extends Model
{
    public $count;

    public $average;

    public $lowest;

    public $highest;

    public $ratingCounts = [];

    public function getTotalRatings(int $rating = null)
    {
        if ($rating === null) {
            return $this->count;
        }

        return ArrayHelper::getValue($this->ratingCounts, $rating - 1);
    }

    /**
     * @inerhitdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['average'], 'number', 'integerOnly' => true];
        $rules[] = [['count', 'lowest', 'highest' ], 'number', 'integerOnly' => true];

        return $rules;
    }
}
