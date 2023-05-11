<?php

namespace nixondesign\reviews\services;

use Craft;
use craft\base\Component;
use nixondesign\reviews\elements\Review;

class Users extends Component
{
    public static function addEditUserReviewsTab(array &$context): void
    {
        if ($context['isNewUser']) {
            return;
        }

        $context['tabs']['reviews'] = [
            'label' => Craft::t('reviews', 'Reviews'),
            'url' => '#reviews',
        ];
    }

    public static function addEditUserReviewsTabContent(array &$context): string
    {
        if (!$context['user'] || $context['isNewUser']) {
            return '';
        }

        $user = Craft::$app->getUsers()->getUserById($context['user']->id);

        return Craft::$app->getView()->renderTemplate('reviews/edit-user-tab', [
            'reviews' => Review::find()->authorId($user->id)->status(null)->all(),
        ]);
    }
}
