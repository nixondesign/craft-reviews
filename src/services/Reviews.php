<?php

namespace nixondesign\reviews\services;

use Craft;
use craft\base\Component;
use nixondesign\reviews\elements\Review;

class Reviews extends Component
{
    /**
     * Returns a Review based on its ID.
     *
     * @param int $reviewId
     * @param int|null $siteId
     * @param array $criteria
     * @return Review|null
     */
    public function getReviewById(int $reviewId, int $siteId = null, array $criteria = []): ?Review
    {
        return Craft::$app->getElements()->getElementById($reviewId, Review::class, $siteId, $criteria);
    }
}
