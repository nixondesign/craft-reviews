<?php

namespace rynpsc\reviews\web\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class ReviewsAsset extends AssetBundle
{
    /**
     * @inerhitdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__;

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'reviews.css',
        ];

        parent::init();
    }
}
