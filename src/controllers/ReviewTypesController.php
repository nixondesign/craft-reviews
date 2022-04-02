<?php

namespace rynpsc\reviews\controllers;

use rynpsc\reviews\Plugin;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\models\ReviewType;

use Craft;
use craft\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ReviewTypesController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requireAdmin();

        return $this->renderTemplate('reviews/settings/types/index', [
            'reviewTypes' => Plugin::getInstance()->getReviewTypes()->getAllReviewTypes(),
        ]);
    }

    /**
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
                $reviewType = Plugin::getInstance()->getReviewTypes()->getReviewTypeById($reviewTypeId);

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

    public function actionSaveType(): ?Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $reviewTypeId = $this->request->getParam('id');
        $reviewTypesService = Plugin::getInstance()->getReviewTypes();

        if ($reviewTypeId) {
            $reviewType = $reviewTypesService->getReviewTypeById($reviewTypeId);
        } else {
            $reviewType = new ReviewType();
        }

        $reviewType->name = $this->request->getParam('name');
        $reviewType->handle = $this->request->getParam('handle');
        $reviewType->maxRating = $this->request->getParam('maxRating');
        $reviewType->allowGuestReviews = $this->request->getParam('allowGuestReviews');
        $reviewType->requireGuestEmail = $this->request->getParam('requireGuestEmail');
        $reviewType->requireGuestName = $this->request->getParam('requireGuestName');
        $reviewType->defaultStatus = $this->request->getParam('defaultStatus');

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

        $this->setSuccessFlash(Craft::t('reviews', 'Review type saved saved.'));

        return $this->redirectToPostedUrl($reviewType);
    }

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
