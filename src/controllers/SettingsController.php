<?php

namespace rynpsc\reviews\controllers;

use Craft;
use craft\web\Controller;
use rynpsc\reviews\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class SettingsController extends Controller
{
    /**
     * Renders the plugin settings template.
     *
     * @return Response
     */
    public function actionEdit(): Response
    {
        $settings = Plugin::getInstance()->getSettings();

        return $this->renderTemplate('reviews/settings/general', compact('settings'));
    }

    /**
     * Saves the plugin settings.
     *
     * @throws BadRequestHttpException
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();

        $settings = $this->request->getBodyParam('settings', []);

        if (!Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings)) {
            $this->setFailFlash(Craft::t('reviews', 'Couldn’t save settings.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $settings,
            ]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('reviews', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
