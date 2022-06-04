<?php

namespace rynpsc\reviews\services;

use Craft;

use craft\base\Component;
use rynpsc\reviews\elements\Review;

class Reviews extends Component
{
    public function getReviewById(int $reviewId, int $siteId = null, array $criteria = [])
    {
        return Craft::$app->getElements()->getElementById($reviewId, Review::class, $siteId, $criteria);
    }
}
