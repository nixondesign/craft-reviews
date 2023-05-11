<?php

namespace nixondesign\reviews\enums;

abstract class Permissions
{
    public const SAVE_REVIEWS = 'reviews-saveReviews';
    public const SAVE_PEER_REVIEWS = 'reviews-savePeerReviews';
    public const DELETE_REVIEWS = 'reviews-deleteReviews';
    public const DELETE_PEER_REVIEWS = 'reviews-deletePeerReviews';
    public const VIEW_REVIEWS = 'reviews-viewReviews';
    public const VIEW_PEER_REVIEWS = 'reviews-viewPeerReviews';
}
