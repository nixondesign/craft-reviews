<?php

declare(strict_types=1);

use craft\ecs\SetList;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function(ECSConfig $ecsConfig): void {
    $ecsConfig->sets([SetList::CRAFT_CMS_4]);

    $ecsConfig->parallel();
    $ecsConfig->paths([
        __DIR__ . '/src',
        __FILE__,
    ]);
};
