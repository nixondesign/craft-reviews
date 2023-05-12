<?php

namespace nixondesign\reviews\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\helpers\Json;

/**
 * m230511_150323_change_namespace migration.
 */
class m230511_150323_change_namespace extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $schemaVersion = $projectConfig->get('plugins.reviews.schemaVersion', true);

        if (version_compare($schemaVersion, '2.0.0', '>=')) {
            return true;
        }

        $this->update(
            CraftTable::ELEMENTS,
            ['type' => 'nixondesign\reviews\elements\Review'],
            'type = rynpsc\reviews\elements\Review',
        );

        $this->renameFieldLayoutElements(
            Craft::$app->getFields()->getLayoutsByType('rynpsc\\reviews\\elements\\Review'),
            'rynpsc\\reviews\\fieldlayoutelements\\ReviewTitleField',
            'nixondesign\\reviews\\fieldlayoutelements\\ReviewTitleField',
        );

        $this->renameFieldLayoutElements(
            Craft::$app->getFields()->getLayoutsByType('rynpsc\\reviews\\elements\\Review'),
            'rynpsc\\reviews\\fieldlayoutelements\\RatingField',
            'nixondesign\\reviews\\fieldlayoutelements\\RatingField',
        );

        $this->renameFieldLayoutElements(
            Craft::$app->getFields()->getLayoutsByType('rynpsc\\reviews\\elements\\Review'),
            'rynpsc\\reviews\\fieldlayoutelements\\ReviewField',
            'nixondesign\\reviews\\fieldlayoutelements\\ReviewField',
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230511_150323_change_namespace cannot be reverted.\n";
        return false;
    }

    private function renameFieldLayoutElements(array $fieldLayouts, string $old, string $new): void
    {
        foreach ($fieldLayouts as $fieldLayout) {
            if ($fieldLayout->id === null) {
                continue;
            }

            $fieldLayoutTabs = (new Query())
                ->select(['id', 'elements'])
                ->from([CraftTable::FIELDLAYOUTTABS])
                ->where(['layoutId' => $fieldLayout->id])
                ->all();

            foreach ($fieldLayoutTabs as $fieldLayoutTab) {
                $elementConfigs = Json::decodeIfJson($fieldLayoutTab['elements']);

                if (is_array($elementConfigs)) {
                    foreach ($elementConfigs as &$elementConfig) {
                        if ($elementConfig['type'] === $old) {
                            $elementConfig['type'] = $new;

                            $this->update(
                                CraftTable::FIELDLAYOUTTABS,
                                ['elements' => Json::encode($elementConfigs)],
                                ['id' => $fieldLayoutTab['id']]
                            );
                        }
                    }
                }
            }
        }
    }
}
