<?php

namespace rynpsc\reviews\services;

use rynpsc\reviews\elements\Review;

use Craft;
use craft\base\Component;

class Reviews extends Component
{
    public function getReviewById(int $reviewId, int $siteId = null, array $criteria = [])
    {
        return Craft::$app->getElements()->getElementById($reviewId, Review::class, $siteId, $criteria);
    }
}
