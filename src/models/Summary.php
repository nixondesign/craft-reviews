<?php

namespace rynpsc\reviews\models;

use craft\base\Model;
use Exception;
use yii\helpers\ArrayHelper;

class Summary extends Model
{
    /**
     * @var int
     */
    public int $count = 0;

    /**
     * @var int
     */
    public int $average = 0;

    /**
     * @var int|null
     */
    public ?int $lowest = null;

    /**
     * @var int|null
     */
    public ?int $highest = null;

    /**
     * @var array
     */
    private array $ratingCounts = [];

    /**
     * Gets the number of ratings with the given value.
     *
     * @param int|null $rating
     * @return int
     * @throws Exception
     */
    public function getTotalRatings(int $rating = null): int
    {
        if ($rating === null) {
            return $this->count;
        }

        return (int)ArrayHelper::getValue($this->ratingCounts, $rating - 1);
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
