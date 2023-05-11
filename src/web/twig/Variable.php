<?php

namespace nixondesign\reviews\web\twig;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Html;

use craft\helpers\Template;
use DateTime;
use nixondesign\reviews\elements\db\ReviewQuery;
use nixondesign\reviews\elements\Review;
use nixondesign\reviews\Plugin;
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

    public function protect(array $attributes = []): Markup
    {
        $settings = Plugin::getInstance()->getSettings();

        $seconds = $settings->minimumSubmitTime;
        $timestamp = (new DateTime())->modify("+ {$seconds} seconds")->getTimestamp();
        $timestamp = Craft::$app->getSecurity()->hashData($timestamp);

        if (!ArrayHelper::keyExists('autocomplete', $attributes)) {
            $attributes['autocomplete'] = 'off';
        }

        $type = ArrayHelper::getValue($attributes, 'type', 'hidden');

        $output = [
            Html::input($type, $settings->honeypotFieldName, null, $attributes),
            Html::input($type, $settings->submissionTimeFieldName, $timestamp, $attributes),
        ];

        return Template::raw(implode('', $output));
    }
}
