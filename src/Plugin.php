<?php

namespace nixondesign\reviews;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\console\Controller;
use craft\console\controllers\ResaveController;
use craft\events\DefineConsoleActionsEvent;
use craft\events\DefineElementInnerHtmlEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\Cp;
use craft\models\FieldLayout;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use nixondesign\reviews\elements\Review;
use nixondesign\reviews\enums\Permissions;
use nixondesign\reviews\enums\ProjectConfig;
use nixondesign\reviews\fieldlayoutelements\RatingField;
use nixondesign\reviews\fieldlayoutelements\ReviewField;
use nixondesign\reviews\fieldlayoutelements\ReviewTitleField;
use nixondesign\reviews\models\Settings;
use nixondesign\reviews\services\Reviews;
use nixondesign\reviews\services\ReviewTypes;
use nixondesign\reviews\services\Users;
use nixondesign\reviews\web\twig\Variable;
use yii\base\Event;

/**
 * @property-read mixed $cpNavItem
 * @property-read Reviews $reviews
 * @property-read ReviewTypes $reviewTypes
 */
class Plugin extends BasePlugin
{
    /**
     * @inheritdoc
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public string $schemaVersion = '2.0.0';

    /**
     * @inerhitdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'reviews' => ['class' => Reviews::class],
                'reviewTypes' => ['class' => ReviewTypes::class],
                'users' => ['class' => Users::class],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->registerElementEvents();
        $this->registerProjectConfigEventListeners();
        $this->registerTemplateHooks();
        $this->registerUserPermissions();
        $this->registerVariables();

        $request = Craft::$app->getRequest();

        if ($request->getIsCpRequest()) {
            $this->registerCpRoutes();
            $this->defineFieldLayoutFields();
        }

        if ($request->getIsConsoleRequest()) {
            $this->registerResaveController();
        }
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($this->getReviewTypes()->getTotalEditableReviewTypes() === 0) {
            return null;
        }

        $item['label'] = Craft::t('reviews', 'Reviews');

        if ($this->settings->showSidebarBadge) {
            $item['badgeCount'] = Craft::configure(Review::find(), [
                'site' => '*',
                'status' => null,
                'moderationStatus' => Review::STATUS_PENDING,
            ])->count();
        }

        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $item['subnav']['reviews'] = [
                'label' => Craft::t('reviews', 'Reviews'),
                'url' => 'reviews',
            ];

            $item['subnav']['settings'] = [
                'label' => Craft::t('reviews', 'Settings'),
                'url' => 'reviews/settings',
            ];
        }

        return $item;
    }

    /**
     * Returns the reviews service.
     */
    public function getReviews(): Reviews
    {
        return $this->get('reviews');
    }

    /**
     * Returns the review types service.
     */
    public function getReviewTypes(): ReviewTypes
    {
        return $this->get('reviewTypes');
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('reviews/settings', [
            'settings' => $this->getSettings(),
        ]);
    }

    private function defineFieldLayoutFields(): void
    {
        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_NATIVE_FIELDS,
            static function(DefineFieldLayoutFieldsEvent $event) {
                $fieldLayout = $event->sender;

                if ($fieldLayout->type !== Review::class) {
                    return;
                }

                $event->fields[] = ReviewTitleField::class;
                $event->fields[] = RatingField::class;
                $event->fields[] = ReviewField::class;
            }
        );
    }

    private function registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'reviews' => 'reviews/reviews/index',
                'reviews/<typeHandle:{handle}>/<elementId:\d+><slug:(?:-[^\/]*)?>' => 'elements/edit',
                'reviews/settings' => ['template' => 'reviews/settings'],
                'reviews/settings/general' => 'reviews/settings/edit',
                'reviews/settings/types' => 'reviews/review-types/index',
                'reviews/settings/types/new' => 'reviews/review-types/edit-type',
                'reviews/settings/types/<reviewTypeId:\d+>' => 'reviews/review-types/edit-type',
            ]);
        });
    }

    private function registerElementEvents(): void
    {
        Event::on(
            Cp::class,
            Cp::EVENT_DEFINE_ELEMENT_INNER_HTML,
            static function(DefineElementInnerHtmlEvent $event) {
                if (!($event->element instanceof Review)) {
                    return;
                }

                $event->innerHtml = Craft::$app->getView()->renderTemplate('reviews/elements/_element', [
                    'review' => $event->element,
                ]);
            }
        );
    }

    private function registerProjectConfigEventListeners(): void
    {
        $reviewTypesService = $this->getReviewTypes();

        Craft::$app->projectConfig
            ->onAdd(ProjectConfig::PATH_REVIEW_TYPES . '.{uid}', [$reviewTypesService, 'handleChangedReviewType'])
            ->onUpdate(ProjectConfig::PATH_REVIEW_TYPES . '.{uid}', [$reviewTypesService, 'handleChangedReviewType'])
            ->onRemove(ProjectConfig::PATH_REVIEW_TYPES . '.{uid}', [$reviewTypesService, 'handleDeletedReviewType']);
    }

    private function registerResaveController(): void
    {
        Event::on(
            ResaveController::class,
            Controller::EVENT_DEFINE_ACTIONS,
            static function(DefineConsoleActionsEvent $event) {
                $event->actions['reviews'] = [
                    'options' => ['type'],
                    'helpSummary' => 'Re-saves reviews.',
                    'optionsHelp' => ['type' => 'The review type handle(s) of the reviews to resave.'],
                    'action' => function(): int {
                        $criteria = [];

                        // @var ResaveController $controller
                        $controller = Craft::$app->controller;

                        if ($controller->type) {
                            $criteria['type'] = explode(',', $controller->type);
                        }

                        return $controller->resaveElements(Review::class, $criteria);
                    },
                ];
            }
        );
    }

    private function registerTemplateHooks(): void
    {
        $view = Craft::$app->getView();

        if ($this->getSettings()->showUserReviewsTab && Craft::$app->getRequest()->getIsCpRequest()) {
            $view->hook('cp.users.edit', [Users::class, 'addEditUserReviewsTab']);
            $view->hook('cp.users.edit.content', [Users::class, 'addEditUserReviewsTabContent']);
        }
    }

    private function registerUserPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $permissions = [];
                $reviewTypes = $this->getReviewTypes()->getAllReviewTypes();

                foreach ($reviewTypes as $reviewType) {
                    $permissions[$reviewType->getPermissionKey(Permissions::VIEW_REVIEWS)] = [
                        'label' => Craft::t('reviews', 'View “{type}” reviews', ['type' => $reviewType->name]),

                        'nested' => [
                            $reviewType->getPermissionKey(Permissions::SAVE_REVIEWS) => [
                                'label' => Craft::t('reviews', 'Save reviews'),
                            ],

                            $reviewType->getPermissionKey(Permissions::DELETE_REVIEWS) => [
                                'label' => Craft::t('reviews', 'Delete reviews'),
                            ],

                            $reviewType->getPermissionKey(Permissions::VIEW_PEER_REVIEWS) => [
                                'label' => Craft::t('reviews', 'View other users’ reviews'),

                                'nested' => [
                                    $reviewType->getPermissionKey(Permissions::SAVE_PEER_REVIEWS) => [
                                        'label' => Craft::t('reviews', 'Save other users’ reviews'),
                                    ],

                                    $reviewType->getPermissionKey(Permissions::DELETE_PEER_REVIEWS) => [
                                        'label' => Craft::t('reviews', 'Delete other users’ reviews'),
                                    ],
                                ],
                            ],
                        ],
                    ];
                }

                $event->permissions[] = [
                    'heading' => Craft::t('reviews', 'Reviews'),
                    'permissions' => $permissions,
                ];
            }
        );
    }

    private function registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $e) {
                /** @var CraftVariable $variable */
                $variable = $e->sender;

                $variable->set('reviews', Variable::class);
            }
        );
    }
}
