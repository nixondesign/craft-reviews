<?php

namespace rynpsc\reviews\controllers;

use rynpsc\reviews\Plugin;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\enums\Permissions;
use rynpsc\reviews\web\assets\ReviewsAsset;

use Craft;
use DateTime;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ReviewsController extends Controller
{
    protected $allowAnonymous = ['save-review'];

    public function actionIndex(): Response
    {
        return $this->renderTemplate('reviews/elements/_index.twig');
    }

    public function actionEditReview(int $reviewId = null, Review $review = null): Response
    {
        $this->view->registerAssetBundle(ReviewsAsset::class);

        if ($review === null) {
            if ($reviewId === null) {
                $review = new Review();
            } else {
               $review = Plugin::getInstance()->getReviews()->getReviewById($reviewId);
            }

            if ($review === null) {
                throw new NotFoundHttpException(Craft::t('reviews', 'Review not found'));
            }
        }

        $this->requirePermission(Permissions::VIEW_REVIEWS . ':' . $review->getType()->uid);

        return $this->renderTemplate('reviews/elements/_edit', [
            'review' => $review,
             'continueEditingUrl' => "reviews/{id}",
        ]);
    }

    public function actionSaveReview(): ?Response
    {
        $this->requirePostRequest();

        $review = $this->getReviewModel();
        $session = Craft::$app->getSession();

        if ($this->getIsSpam()) {
            return $this->redirectToPostedUrl($review);
        }

        if (!$review->getType()->allowGuestReviews && !Craft::$app->getUser()->getIdentity()) {
            throw new ForbiddenHttpException('You must be logged in to post a review');
        }

        if ($review->enabled) {
            $review->setScenario($review::SCENARIO_LIVE);
        }

        if (!Craft::$app->elements->saveElement($review, true)) {
            $session->setError(Craft::t('reviews', 'Unable to save review'));

            Craft::$app->getUrlManager()->setRouteParams([
                'review' => $review,
            ]);

            return null;
        }

        $session->setNotice(Craft::t('reviews', 'Review saved.'));

        return $this->redirectToPostedUrl($review);
    }

    public function actionDeleteReview(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        $id = $request->getRequiredBodyParam('reviewId');

        $review = Review::find()->id($id)->moderationStatus(null)->one();

        $permissionSuffix = ':' . $review->getType()->uid;

        $this->requirePermission(Permissions::DELETE_REVIEWS . $permissionSuffix);

        if (!Craft::$app->getElements()->deleteElementById($id)) {
            $session->setError(Craft::t('reviews', 'Unable to delete review.'));
        }

        $session->setNotice(Craft::t('reviews', 'Review deleted.'));

        return $this->redirectToPostedUrl();
    }

    private function getReviewModel()
    {
        $typeId = $this->request->getBodyParam('typeId');
        $reviewId = $this->request->getBodyParam('reviewId');

        if ($reviewId) {
            $review = Plugin::getInstance()->getReviews()->getReviewById($reviewId);

            if (!$review) {
                throw new NotFoundHttpException('Review not found');
            }
        } else {
            $review = new Review();
        }

        $userId = $this->request->getParam('userId');
        $elementId = $this->request->getBodyParam('elementId');

        if ($userId === null) {
            $user = Craft::$app->getUser()->getIdentity();

            if ($user) {
                $userId = $user->id;
            }
        }

        if ($userId) {
            $review->userId = $userId;
        } else {
            $review->email = $this->request->getBodyParam('email');
            $review->fullName = $this->request->getBodyParam('fullName');
        }

        if (($date = $this->request->getBodyParam('submissionDate')) !== null) {
            $review->submissionDate = DateTimeHelper::toDateTime($date) ?: null;
        }

        $review->typeId = $typeId;
        $review->elementId = $elementId;
        $review->siteId = $this->request->getBodyParam('siteId');
        $review->rating = $this->request->getBodyParam('rating');
        $review->review = $this->request->getBodyParam('review');
        $review->moderationStatus = $this->request->getParam('moderationStatus', $review->moderationStatus);
        $review->enabled = $this->request->getBodyParam('enabled', true);

        $review->fieldLayoutId = $review->getType()->fieldLayoutId;

        $fieldsLocation = $this->request->getParam('fieldsLocation', 'fields');
        $review->setFieldValuesFromRequest($fieldsLocation);

        return $review;
    }

    private function getIsSpam(): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($this->request->isCpRequest) {
            return false;
        }

        if (!$settings->enableSpamProtection) {
            return false;
        }

        if ($this->request->getRequiredBodyParam($settings->honeypotFieldName)) {
            return true;
        }

        if (!$this->request->getBodyParam($settings->submissionTimeFieldName)) {
            throw new BadRequestHttpException('Request missing required body param');
        }

        $timestamp = $this->request->getValidatedBodyParam($settings->submissionTimeFieldName);
        $aboveMinimumSubmitTime = new DateTime() >= DateTimeHelper::toDateTime($timestamp);

        if (!$aboveMinimumSubmitTime) {
            return true;
        }

        return false;
    }
}
