<?php

namespace rynpsc\reviews\models;

use craft\base\Model;
use Exception;
use yii\helpers\ArrayHelper;

class Summary extends Model
{
    /**
     * @var int The number of reviews.
     */
    public int $count = 0;

    /**
     * @var int The average rating.
     */
    public int $average = 0;

    /**
     * @var int|null The lowest rating.
     */
    public ?int $lowest = null;

    /**
     * @var int|null The highest rating.
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
