<?php

namespace rynpsc\reviews\migrations;

use rynpsc\reviews\Plugin;
use rynpsc\reviews\db\Table;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\models\ReviewType;

use Craft;
use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\helpers\MigrationHelper;
use craft\records\FieldLayout;

class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTables();
        $this->addForeignKeys();
        $this->insertDefaultData();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropForeignKeys();
        $this->dropTables();

        return true;
    }

    public function createTables(): void
    {
        if (!$this->db->tableExists(Table::REVIEWS)) {
            $this->createTable(Table::REVIEWS, [
                'id' => $this->primaryKey(),
                'elementId' => $this->integer(),
                'siteId' => $this->integer(),
                'typeId' => $this->integer(),
                'userId' => $this->integer(),
                'moderationStatus' => $this->string(),
                'review' => $this->text(),
                'fullName' => $this->text(),
                'email' => $this->text(),
                'rating' => $this->integer(),
                'submissionDate' => $this->dateTime(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        if (!$this->db->tableExists(Table::REVIEWTYPES)) {
            $this->createTable(Table::REVIEWTYPES, [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull(),
                'handle' => $this->string()->notNull(),
                'maxRating' => $this->integer()->notNull(),
                'defaultStatus' => $this->string(),
                'allowGuestReviews' => $this->boolean(),
                'requireGuestEmail' => $this->boolean(),
                'requireGuestName' => $this->boolean(),
                'fieldLayoutId' => $this->integer(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }
    }

    public function dropTables(): void
    {
        $this->dropTableIfExists(Table::REVIEWS);
        $this->dropTableIfExists(Table::REVIEWTYPES);
    }

    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, Table::REVIEWS, 'id', CraftTable::ELEMENTS, 'id', 'CASCADE', null);
        $this->addForeignKey(null, Table::REVIEWS, 'siteId', CraftTable::SITES, 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::REVIEWS, 'userId', CraftTable::USERS, 'id', 'SET NULL', null);
    }

    public function dropForeignKeys(): void
    {
        MigrationHelper::dropAllForeignKeysOnTable(Table::REVIEWS, $this);
    }

    private function insertDefaultData(): void
    {
        $installed = (Craft::$app->projectConfig->get('plugins.reviews', true) !== null);
        $configExists = (Craft::$app->projectConfig->get('reviews', true) !== null);

        if ($installed && $configExists) {
            return;
        }

        $this->insert(FieldLayout::tableName(), ['type' => Review::class]);
        $fieldLayoutId = $this->db->getLastInsertID(FieldLayout::tableName());

        $data = [
            'name' => 'Default',
            'handle' => 'default',
            'fieldLayoutId' => $fieldLayoutId,
        ];

        $reviewType = new ReviewType($data);

        Plugin::getInstance()->getReviewTypes()->saveReviewType($reviewType);
    }
}
