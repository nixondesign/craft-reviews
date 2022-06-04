<?php

namespace rynpsc\reviews\controllers;

use rynpsc\reviews\Plugin;
use rynpsc\reviews\elements\Review;

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
    /**
     * @inerhitdoc
     */
    protected int|bool|array $allowAnonymous = ['save-review'];

    public function actionIndex(): Response
    {
        return $this->renderTemplate('reviews/elements/_index.twig');
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

        $review->setScenario($review::SCENARIO_LIVE);

        if (!Craft::$app->elements->saveElement($review)) {
            $session->setError(Craft::t('reviews', 'Unable to save review'));

            Craft::$app->getUrlManager()->setRouteParams([
                'review' => $review,
            ]);

            return null;
        }

        $session->setNotice(Craft::t('reviews', 'Review saved.'));

        return $this->redirectToPostedUrl($review);
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
            $review->typeId = $typeId;
            $review->siteId = $this->request->getBodyParam('siteId');
            $review->fieldLayoutId = $review->getType()->fieldLayoutId;
            $review->elementId = $this->request->getRequiredBodyParam('elementId');
        }

        $user = Craft::$app->getUser()->getIdentity();

        if ($user && $user->id) {
            $review->authorId = $user->id;
        }

        $review->email = $this->request->getBodyParam('email');
        $review->fullName = $this->request->getBodyParam('fullName');
        $review->title = $this->request->getBodyParam('title');
        $review->rating = $this->request->getBodyParam('rating');
        $review->review = $this->request->getBodyParam('review');

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
