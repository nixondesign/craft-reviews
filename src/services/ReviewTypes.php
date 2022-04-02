<?php

namespace rynpsc\reviews\services;

use rynpsc\reviews\db\Table;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\enums\Permissions;
use rynpsc\reviews\models\ReviewType;
use rynpsc\reviews\records\ReviewType as ReviewTypeRecord;

use Craft;
use Throwable;
use craft\base\Component;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;

class ReviewTypes extends Component
{
    public const PROJECT_CONFIG_REVIEW_TYPES_KEY = 'reviews.reviewTypes';

    public function getAllReviewTypes(): array
    {
        $results = (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'uid',
            ])
            ->from(Table::REVIEWTYPES)
            ->all();

        $all = [];

        foreach ($results as $result) {
            $all[] = new ReviewType($result);
        }

        return $all;
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

    public function getReviewTypeById(int $id): ?ReviewType
    {
        $record = ReviewTypeRecord::findOne(['id' => $id]);

        if ($record === null) {
            return null;
        }

        $model = new ReviewType();
        $model->setAttributes($record->getAttributes(), false);

        return $model;
    }

    public function saveReviewType(ReviewType $reviewType, bool $runValidation = true): bool
    {
        $isNew = ($reviewType->id === null);

        if ($runValidation && !$reviewType->validate()) {
            return false;
        }

        if ($isNew) {
            $reviewType->uid = StringHelper::UUID();
        } elseif (!$reviewType->uid) {
            $reviewTypeRecord = ReviewTypeRecord::findOne(['id' => $reviewType->id]);

            if ($reviewTypeRecord === null) {
                throw new \Exception("No review type exists with the ID '{$reviewType->id}'");
            }

            $reviewType->uid = $reviewTypeRecord->uid;
        }

        $projectConfig = Craft::$app->getProjectConfig();

        $projectConfigData = [
            'name' => $reviewType->name,
            'handle' => $reviewType->handle,
            'maxRating' => $reviewType->maxRating,
            'allowGuestReviews' => $reviewType->allowGuestReviews,
            'requireGuestEmail' => $reviewType->requireGuestEmail,
            'requireGuestName' => $reviewType->requireGuestName,
            'defaultStatus' => $reviewType->defaultStatus,
        ];

        // ---
        $fieldLayout = $reviewType->getFieldLayout();
        $fieldLayoutConfig = $fieldLayout->getConfig();

        if ($fieldLayoutConfig) {
            if (!$fieldLayout->id) {
                $layoutUid = $fieldLayout->uid = StringHelper::UUID();
            } else {
                $layoutUid = Db::uidById(\craft\db\Table::FIELDLAYOUTS, $fieldLayout->id);
            }

            $projectConfigData['fieldLayouts'] = [
                $layoutUid => $fieldLayoutConfig,
            ];
        }
        // ---

        $projectConfigPath = self::PROJECT_CONFIG_REVIEW_TYPES_KEY . ".{$reviewType->uid}";
        $projectConfig->set($projectConfigPath, $projectConfigData);

        if ($isNew) {
            $reviewType->id = Db::idByUid(Table::REVIEWTYPES, $reviewType->uid);
        }

        return true;
    }

    public function deleteReviewTypeById(int $id): bool
    {
        $reviewType = $this->getReviewTypeById($id);
        $projectConfigPath = self::PROJECT_CONFIG_REVIEW_TYPES_KEY . ".{$reviewType->uid}";

        Craft::$app->getProjectConfig()->remove($projectConfigPath);

        return true;
    }

    public function handleChangedReviewType(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];

        // Make sure fields and sites are processed
        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigHelper::ensureAllFieldsProcessed();

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        $data = $event->newValue;

        try {
            $reviewTypeRecord = $this->getReviewTypeRecordByUid($uid);

            $reviewTypeRecord->uid = $uid;
            $reviewTypeRecord->name = $data['name'];
            $reviewTypeRecord->handle = $data['handle'];
            $reviewTypeRecord->maxRating = $data['maxRating'];
            $reviewTypeRecord->allowGuestReviews = $data['allowGuestReviews'];
            $reviewTypeRecord->requireGuestEmail = $data['requireGuestEmail'];
            $reviewTypeRecord->requireGuestName = $data['requireGuestName'];
            $reviewTypeRecord->defaultStatus = $data['defaultStatus'];

            $fieldsService = Craft::$app->getFields();

            if (!empty($data['fieldLayouts']) && !empty($config = reset($data['fieldLayouts']))) {
                $layout = FieldLayout::createFromConfig($config);
                $layout->id = $reviewTypeRecord->fieldLayoutId;
                $layout->type = Review::class;
                $layout->uid = key($data['fieldLayouts']);
                $fieldsService->saveLayout($layout);
                $reviewTypeRecord->fieldLayoutId = $layout->id;
            } else if ($reviewTypeRecord->fieldLayoutId) {
                $fieldsService->deleteLayoutById($reviewTypeRecord->fieldLayoutId);
                $reviewTypeRecord->fieldLayoutId = null;
            }

            $reviewTypeRecord->save(false);
            $transaction->commit();
        } catch (Throwable $exception) {
            $transaction->rollBack();
            throw $exception;
        }
    }

    public function handleDeletedReviewType(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();
        $reviewTypeRecord = $this->getReviewTypeRecordByUid($uid);

        if ($reviewTypeRecord->id === null) {
            return;
        }

        try {
            $reviews = Review::find()
                ->anyStatus()
                ->typeId($reviewTypeRecord->id)
                ->limit(null)
                ->all();

            foreach ($reviews as $review) {
                Craft::$app->getElements()->deleteElement($review);
            }

            $reviewTypeRecord->delete();
            $transaction->commit();
        } catch (Throwable $exception) {
            $transaction->rollBack();

            throw $exception;
        }
    }

    private function getReviewTypeRecordByUid(string $uid): ReviewTypeRecord
    {
        $reviewType = ReviewTypeRecord::findOne(['uid' => $uid]);

        if ($reviewType) {
            return $reviewType;
        }

        return new ReviewTypeRecord();
    }
}
