<?php

namespace rynpsc\reviews\web\twig;

use rynpsc\reviews\Plugin;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\elements\db\ReviewQuery;

use Craft;
use DateTime;
use craft\helpers\Html;
use craft\helpers\Template;
use Twig\Markup;
use yii\base\Behavior;

class Variable extends Behavior
{
    public function reviews($criteria = null): ReviewQuery
    {
        $query = Review::find();

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    public function protect(): Markup
    {
        $settings = Plugin::getInstance()->getSettings();

        $seconds = $settings->minimumSubmitTime;
        $timestamp = (new DateTime())->modify("+ {$seconds} seconds")->getTimestamp();
        $timestamp = Craft::$app->getSecurity()->hashData($timestamp);

        $output = [
            Html::input('text', $settings->honeypotFieldName, null, ['autocomplete' => 'off']),
            Html::input('text', $settings->submissionTimeFieldName, $timestamp, ['autocomplete' => 'off']),
        ];

        return Template::raw(implode('', $output));
    }
}
