<?php

namespace nixondesign\reviews\controllers;

use Craft;
use craft\web\Controller;
use nixondesign\reviews\elements\Review;
use nixondesign\reviews\models\ReviewType;
use nixondesign\reviews\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ReviewTypesController extends Controller
{
    /**
     * Renders the review types control panel index page.
     *
     * @return Response
     * @throws ForbiddenHttpException
     */
    public function actionIndex(): Response
    {
        $this->requireAdmin();

        return $this->renderTemplate('reviews/settings/types/index', [
            'reviewTypes' => Plugin::getInstance()->getReviewTypes()->getAllReviewTypes(),
        ]);
    }

    /**
     * Renders an individual review types control panel page.
     *
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionEditType(int $reviewTypeId = null, ReviewType $reviewType = null): Response
    {
        $this->requireAdmin();

        $variables = [
            'isNew' => false,
        ];

        if ($reviewType === null) {
            if ($reviewTypeId !== null) {
                $reviewType = Plugin::getInstance()->getReviewTypes()->getTypeById($reviewTypeId);

                if ($reviewType === null) {
                    throw new NotFoundHttpException('Review type not found');
                }

                $variables['title'] = Craft::t('reviews', $reviewType->name);
            } else {
                $reviewType = new ReviewType();
                $variables['isNew'] = true;
                $variables['title'] = Craft::t('reviews', 'Create a new review type');
            }
        } else {
            $variables['title'] = Craft::t('reviews', $reviewType->name);
        }

        $variables['reviewType'] = $reviewType;
        $variables['statuses'] = Review::moderationStatuses();

        return $this->renderTemplate('reviews/settings/types/_edit', $variables);
    }

    /**
     * Saves a review type.
     *
     * @return Response|null
     * @throws ForbiddenHttpException
     * @throws BadRequestHttpException
     */
    public function actionSaveType(): ?Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $reviewTypeId = $this->request->getParam('id');
        $reviewTypesService = Plugin::getInstance()->getReviewTypes();

        if ($reviewTypeId) {
            $reviewType = $reviewTypesService->getTypeById($reviewTypeId);
        } else {
            $reviewType = new ReviewType();
        }

        $reviewType->name = $this->request->getBodyParam('name', $reviewType->name);
        $reviewType->handle = $this->request->getBodyParam('handle', $reviewType->handle);
        $reviewType->maxRating = (int)$this->request->getBodyParam('maxRating', $reviewType->maxRating);
        $reviewType->allowGuestReviews = (bool)$this->request->getBodyParam('allowGuestReviews', $reviewType->allowGuestReviews);
        $reviewType->requireFullName = (bool)$this->request->getBodyParam('requireFullName', $reviewType->requireFullName);
        $reviewType->defaultStatus = $this->request->getBodyParam('defaultStatus', $reviewType->defaultStatus);
        $reviewType->hasTitleField = (bool)$this->request->getBodyParam('hasTitleField', $reviewType->hasTitleField);
        $reviewType->titleFormat = $this->request->getBodyParam('titleFormat', $reviewType->titleFormat);

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Review::class;
        $reviewType->setFieldLayout($fieldLayout);

        if (!$reviewTypesService->saveReviewType($reviewType)) {
            $this->setFailFlash(Craft::t('reviews', 'Couldn’t save the review type.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'reviewType' => $reviewType,
            ]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('reviews', 'Review type saved.'));

        return $this->redirectToPostedUrl($reviewType);
    }

    /**
     * Deletes a review type.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionDeleteReviewType(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $reviewTypeId = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getReviewTypes()->deleteReviewTypeById($reviewTypeId);

        return $this->asJson([
            'success' => true,
        ]);
    }
}
