<?php

namespace rynpsc\reviews\console\controllers\utils;

use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\helpers\Console;
use rynpsc\reviews\db\Table;
use rynpsc\reviews\elements\Review;
use yii\console\ExitCode;

/**
 * Prunes reviews where the source element no longer exists.
 */
class PruneOrphanedReviewsController extends Controller
{
    /**
     * Prunes reviews where the source element no longer exists.
     */
    public function actionIndex()
    {
        $this->stdout('Finding reviews with deleted elements ... ');

        $elementIds = (new Query())
            ->select('id')
            ->from(\craft\db\Table::ELEMENTS);

        $query = (new Query())
            ->from(['reviews' => Table::REVIEWS])
            ->where(['not in', 'elementId', $elementIds]);

        $results = $query->all();

        $this->stdout('done' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        if (empty($results)) {
            $this->stdout('Nothing to prune' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
            return ExitCode::OK;
        }

        $failed = false;
        $current = 1;
        $count = count($results);
        $this->stdout("Pruning {$count} reviews ..." . PHP_EOL);

        $elementsService = Craft::$app->getElements();

        foreach ($results as $result) {
            $id = $result['id'];

            $this->stdout("    - [{$current}/{$count}] Deleting review ({$id}) ... ");

            if ($elementsService->deleteElementById($id, Review::class)) {
                $this->stdout('done' . PHP_EOL, Console::FG_GREEN);
            } else {
                $failed = true;
                $this->stderr('error: ' . PHP_EOL, Console::FG_RED);
            }

            $current++;
        }

        $this->stdout("Finished deleting reviews." . PHP_EOL . PHP_EOL, Console::FG_YELLOW);

        return $failed ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
