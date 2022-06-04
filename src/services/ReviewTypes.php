<?php

namespace rynpsc\reviews\services;

use Craft;
use craft\base\Component;
use craft\base\MemoizableArray;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;

use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\Queue;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\queue\jobs\ResaveElements;
use rynpsc\reviews\db\Table;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\enums\Permissions;
use rynpsc\reviews\enums\ProjectConfig;
use rynpsc\reviews\models\ReviewType;
use rynpsc\reviews\records\ReviewType as ReviewTypeRecord;
use Throwable;

class ReviewTypes extends Component
{
    private ?MemoizableArray $types = null;

    private function types()
    {
        if (isset($this->types)) {
            return $this->types;
        }

        $types = [];

        foreach ($this->createTypeQuery()->all() as $result) {
            $types[] = new ReviewType($result);
        }

        return $this->types = new MemoizableArray($types);
    }

    public function getAllReviewTypes(): array
    {
        return $this->types()->all();
    }

    public function getEditableReviewTypes(): array
    {
        $user = Craft::$app->getUser()->getIdentity();

        if ($user === null) {
            return [];
        }

        return ArrayHelper::where($this->getAllReviewTypes(), function(ReviewType $reviewType) use ($user) {
            return $user->can(Permissions::VIEW_REVIEWS . ':' . $reviewType->uid);
        });
    }

    public function getTotalEditableReviewTypes(): int
    {
        return count($this->getEditableReviewTypes());
    }

    public function getTypeByHandle(string $handle): ?ReviewType
    {
        return $this->types()->firstWhere('handle', $handle, true);
    }

    public function getTypeById(int $id): ?ReviewType
    {
        return $this->types()->firstWhere('id', $id, true);
    }

    public function getTypeByUid(string $uid)
    {
        return $this->types()->firstWhere('uid', $uid, true);
    }

    public function saveReviewType(ReviewType $reviewType, bool $runValidation = true): bool
    {
        $isNew = !$reviewType->id;

        if ($runValidation && !$reviewType->validate()) {
            Craft::info('Review type not saved due to validation error.', __METHOD__);

            return false;
        }

        if ($isNew) {
            $reviewType->uid = StringHelper::UUID();
        }

        $configPath = ProjectConfig::PATH_REVIEW_TYPES . '.' . $reviewType->uid;

        Craft::$app->getProjectConfig()->set(
            $configPath,
            $reviewType->getConfig(),
            "Save review type “{$reviewType->handle}”"
        );

        if ($isNew) {
            $reviewType->id = Db::idByUid(Table::REVIEWTYPES, $reviewType->uid);
        }

        return true;
    }

    public function deleteReviewTypeById(int $id): bool
    {
        if (!$id) {
            return false;
        }

        $reviewType = $this->getTypeById($id);

        if (!$reviewType) {
            return false;
        }

        return $this->deleteReviewType($reviewType);
    }

    public function deleteReviewType(ReviewType $reviewType): bool
    {
        Craft::$app->getProjectConfig()->remove(
            ProjectConfig::PATH_REVIEW_TYPES . '.' . $reviewType->uid,
            "Delete review type “{$reviewType->handle}”"
        );

        return true;
    }

    public function handleChangedReviewType(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];

        // Make sure fields and sites are processed
        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigHelper::ensureAllFieldsProcessed();

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $data = $event->newValue;

            $reviewTypeRecord = $this->getReviewTypeRecordByUid($uid) ?? new ReviewTypeRecord();

            $reviewTypeRecord->uid = $uid;
            $reviewTypeRecord->setAttributes($data, false);

            $fieldsService = Craft::$app->getFields();

            if (!empty($data['fieldLayouts'])) {
                $layout = FieldLayout::createFromConfig(reset($data['fieldLayouts']));
                $layout->id = $reviewTypeRecord->fieldLayoutId;
                $layout->type = Review::class;
                $layout->uid = key($data['fieldLayouts']);
                $fieldsService->saveLayout($layout, false);
                $reviewTypeRecord->fieldLayoutId = $layout->id;
            } elseif ($reviewTypeRecord->fieldLayoutId) {
                $fieldsService->deleteLayoutById($reviewTypeRecord->fieldLayoutId);
                $reviewTypeRecord->fieldLayoutId = null;
            }

            $resaveRequired = (
                $reviewTypeRecord->titleFormat !== $reviewTypeRecord->getOldAttribute('titleFormat') ||
                $reviewTypeRecord->hasTitleField !== $reviewTypeRecord->getOldAttribute('hasTitleField') ||
                $reviewTypeRecord->fieldLayoutId !== $reviewTypeRecord->getOldAttribute('fieldLayoutId')
            );

            $reviewTypeRecord->save(false);

            $transaction->commit();
        } catch (Throwable $exception) {
            $transaction->rollBack();
            throw $exception;
        }

        if ($resaveRequired) {
            $this->resaveReviewsByTypeId($reviewTypeRecord->id);
        }

        $this->types = null;
        Craft::$app->getElements()->invalidateCachesForElementType(Review::class);
    }

    public function handleDeletedReviewType(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $reviewTypeRecord = $this->getReviewTypeRecordByUid($uid);

        if ($reviewTypeRecord === null) {
            return;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $reviewQuery = Review::find()
                ->limit(null)
                ->status(null)
                ->typeId($reviewTypeRecord->id);

            $elementsService = Craft::$app->getElements();

            foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
                foreach (Db::each($reviewQuery->siteId($siteId)) as $review) {
                    /** @var Review $review */
                    $elementsService->deleteElement($review);
                }
            }

            if ($reviewTypeRecord->fieldLayoutId) {
                Craft::$app->getFields()->deleteFieldById($reviewTypeRecord->fieldLayoutId);
            }

            Craft::$app->getDb()->createCommand()
                ->delete(Table::REVIEWTYPES, ['id' => $reviewTypeRecord->id])
                ->execute();

            $transaction->commit();
        } catch (Throwable $exception) {
            $transaction->rollBack();

            throw $exception;
        }

        Craft::$app->getElements()->invalidateCachesForElementType(Review::class);
    }

    private function resaveReviewsByTypeId(int $id): void
    {
        $type = $this->getTypeById($id);

        $description = Craft::t('reviews', 'Resaving {type} reviews', [
            'type' => $type->name,
        ]);

        Queue::push(new ResaveElements([
            'description' => $description,
            'elementType' => Review::class,
            'criteria' => [
                'typeId' => $id,
                'siteId' => '*',
                'status' => null,
            ],
        ]));
    }

    private function getReviewTypeRecordByUid(string $uid): ?ReviewTypeRecord
    {
        $reviewType = ReviewTypeRecord::findOne(['uid' => $uid]);

        return $reviewType ?? null;
    }

    private function createTypeQuery()
    {
        return (new Query())->select('*')->from(Table::REVIEWTYPES);
    }
}
