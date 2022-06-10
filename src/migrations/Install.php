<?php

namespace rynpsc\reviews\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\helpers\Db;
use craft\records\FieldLayout;
use rynpsc\reviews\db\Table;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\models\ReviewType;
use rynpsc\reviews\Plugin;

class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
        $this->insertDefaultData();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();
        $this->dropProjectConfig();

        $this->delete(CraftTable::FIELDLAYOUTS, ['type' => Review::class]);

        return true;
    }

    public function createTables(): void
    {
        $this->archiveTableIfExists(Table::REVIEWS);
        $this->archiveTableIfExists(Table::REVIEWTYPES);

        $this->createTable(Table::REVIEWS, [
            'id' => $this->primaryKey(),
            'ownerId' => $this->integer(),
            'siteId' => $this->integer(),
            'typeId' => $this->integer(),
            'authorId' => $this->integer(),
            'moderationStatus' => $this->text(),
            'review' => $this->text(),
            'rating' => $this->integer(),
            'submissionDate' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(Table::REVIEWTYPES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'maxRating' => $this->integer()->notNull(),
            'defaultStatus' => $this->string(),
            'allowGuestReviews' => $this->boolean(),
            'requireFullName' => $this->boolean(),
            'hasTitleField' => $this->boolean(),
            'titleFormat' => $this->string(),
            'fieldLayoutId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function dropTables(): void
    {
        $this->dropTableIfExists(Table::REVIEWS);
        $this->dropTableIfExists(Table::REVIEWTYPES);
    }

    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, Table::REVIEWS, 'id', CraftTable::ELEMENTS, 'id', 'CASCADE');
        $this->addForeignKey(null, Table::REVIEWS, 'siteId', CraftTable::SITES, 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::REVIEWS, 'authorId', CraftTable::USERS, 'id', 'SET NULL');
    }

    public function dropForeignKeys(): void
    {
        Db::dropAllForeignKeysToTable(Table::REVIEWS);
        Db::dropForeignKeyIfExists(Table::REVIEWS, 'id');
        Db::dropForeignKeyIfExists(Table::REVIEWS, 'siteId');
        Db::dropForeignKeyIfExists(Table::REVIEWS, 'authorId');
    }

    public function createIndexes(): void
    {
        $this->createIndex(null, Table::REVIEWS, 'siteId');
        $this->createIndex(null, Table::REVIEWS, 'rating');
        $this->createIndex(null, Table::REVIEWS, 'authorId');
        $this->createIndex(null, Table::REVIEWS, 'ownerId');
        $this->createIndex(null, Table::REVIEWS, 'submissionDate');
        $this->createIndex(null, Table::REVIEWTYPES, 'name');
        $this->createIndex(null, Table::REVIEWTYPES, 'handle');
        $this->createIndex(null, Table::REVIEWTYPES, 'fieldLayoutId');
    }

    public function dropProjectConfig(): void
    {
        Craft::$app->projectConfig->remove('reviews');
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
