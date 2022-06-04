<?php

namespace rynpsc\reviews\controllers;

use Craft;

use craft\web\Controller;
use rynpsc\reviews\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class SettingsController extends Controller
{
    public function actionEdit(): Response
    {
        $settings = Plugin::getInstance()->getSettings();

        return $this->renderTemplate('reviews/settings/general', compact('settings'));
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionSaveSettings(): Response
    {
        $this->requirePostRequest();

        $settings = $this->request->getBodyParam('settings', []);

        if (!Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings)) {
            $this->setFailFlash(Craft::t('reviews', 'Couldn’t save settings.'));

            return $this->renderTemplate('reviews/settings/general', compact('settings'));
        }

        $this->setSuccessFlash(Craft::t('reviews', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
