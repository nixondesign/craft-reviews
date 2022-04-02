<?php

namespace rynpsc\reviews;

use rynpsc\reviews\db\Table;
use rynpsc\reviews\elements\Review;
use rynpsc\reviews\enums\Permissions;
use rynpsc\reviews\fieldlayoutelements\RatingField;
use rynpsc\reviews\fieldlayoutelements\ReviewField;
use rynpsc\reviews\models\Settings;
use rynpsc\reviews\services\ReviewTypes;
use rynpsc\reviews\services\Reviews;
use rynpsc\reviews\web\twig\Variable;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\UserEvent;
use craft\models\FieldLayout;
use craft\services\UserPermissions;
use craft\services\Users;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

/**
 * @property-read mixed $cpNavItem
 * @property-read Reviews $reviews
 * @property-read ReviewTypes $reviewTypes
 */
class Plugin extends BasePlugin
{
    public $hasCpSection = true;

    public $hasCpSettings = true;

    public static function addEditUserReviewsTab(array &$context): void
    {
        $context['tabs']['reviews'] = [
            'label' => Craft::t('reviews', 'Reviews'),
            'url' => '#reviews',
        ];
    }

    public static function addEditUserReviewsTabContent(array &$context): string
    {
        if (!$context['user'] || $context['isNewUser']) {
            return '';
        }

        $user = Craft::$app->getUsers()->getUserById($context['user']->id);

        return Craft::$app->getView()->renderTemplate('reviews/edit-user-tab', [
            'user' => $user,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'reviews' => Reviews::class,
            'reviewTypes' => ReviewTypes::class,
        ]);

        $this->registerCpRoutes();
        $this->registerFieldLayout();
        $this->registerProjectConfigEventListeners();
        $this->registerTemplateHooks();
        $this->registerUserPermissions();
        $this->registerUsersListeners();
        $this->registerVariables();
    }

    /**
     * @inheritdoc
     */
    public function beforeInstall(): bool
    {
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80002) {
            Craft::error('Reviews requires PHP 8.0.2 or later.');

            return false;
        }

        return true;
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

        $item['badgeCount'] = Craft::configure(Review::find(), [
            'site' => '*',
            'moderationStatus' => Review::STATUS_PENDING,
        ])->count();

        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $item['subnav'] = [
                'reviews' => [
                    'label' => Craft::t('reviews', 'Reviews'),
                    'url' => 'reviews',
                ],

                'settings' => [
                    'label' => Craft::t('reviews', 'Settings'),
                    'url' => 'reviews/settings',
                ],
            ];
        }

        return $item;
    }

    public function getReviews(): Reviews
    {
        return $this->get('reviews');
    }

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

    private function registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'reviews' => 'reviews/reviews/index',
                'reviews/<reviewId:\d+>' => 'reviews/reviews/edit-review',
                'reviews/new' => 'reviews/reviews/edit-review',
                'reviews/new/<siteHandle:{handle}>' => 'reviews/reviews/edit-review',

                'reviews/settings/types' => 'reviews/review-types/index',
                'reviews/settings/types/new' => 'reviews/review-types/edit-type',
                'reviews/settings/types/<reviewTypeId:\d+>' => 'reviews/review-types/edit-type',

                'reviews/settings' => ['template' => 'reviews/settings'],
                'reviews/settings/general' => 'reviews/settings/edit',
            ]);
        });
    }

    private function registerFieldLayout(): void
    {
        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_STANDARD_FIELDS,
            static function (DefineFieldLayoutFieldsEvent $event) {
                $fieldLayout = $event->sender;

                if ($fieldLayout->type !== Review::class) {
                    return;
                }

                $event->fields[] = RatingField::class;
                $event->fields[] = ReviewField::class;
            }
        );
    }

    private function registerProjectConfigEventListeners(): void
    {
        $reviewTypesService = $this->getReviewTypes();

        Craft::$app->projectConfig
            ->onAdd('reviews.reviewTypes.{uid}', [$reviewTypesService, 'handleChangedReviewType'])
            ->onUpdate('reviews.reviewTypes.{uid}', [$reviewTypesService, 'handleChangedReviewType'])
            ->onRemove('reviews.reviewTypes.{uid}', [$reviewTypesService, 'handleDeletedReviewType']);
    }

    private function registerTemplateHooks(): void
    {
        $view = Craft::$app->getView();

        $view->hook('cp.users.edit', [$this, 'addEditUserReviewsTab']);
        $view->hook('cp.users.edit.content', [$this, 'addEditUserReviewsTabContent']);
        $view->hook('cp.elements.element', [Review::class, 'getReviewElementTitleHtml']);
    }

    private function registerUsersListeners(): void
    {
        Event::on(
            Users::class,
            Users::EVENT_AFTER_ACTIVATE_USER,
            static function (UserEvent $event) {
                $email = $event->user->email;
                $userId = $event->user->id;

                Craft::$app->db->createCommand()->update(
                    Table::REVIEWS,
                    [
                        'userId' => $userId,
                        'fullName' => null,
                        'email' => null,
                    ],
                    'email = :email',
                    [':email' => $email]
                )->execute();
            }
        );
    }

    private function registerUserPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $reviewTypes = $this->getReviewTypes()->getAllReviewTypes();

                $reviewTypePermissions = [];

                foreach ($reviewTypes as $reviewType) {
                    $suffix = ':' . $reviewType->uid;

                    $reviewTypePermissions['reviews-manageReviews' . $suffix] = [
                        'label' => Craft::t('reviews', 'Manage "{title}" reviews', [
                            'title' => Craft::t('reviews', $reviewType->name),
                        ]),

                        'nested' => [
                            Permissions::VIEW_REVIEWS . $suffix => [
                                'label' => Craft::t('reviews', 'Edit reviews'),
                            ],

                            Permissions::DELETE_REVIEWS . $suffix => [
                                'label' => Craft::t('reviews', 'Delete reviews'),
                            ],
                        ]
                    ];
                }

                $event->permissions[Craft::t('reviews', 'Reviews')] = [
                    'reviews-manageReviews' => [
                        'label' => Craft::t('reviews', 'Mange reviews'),
                        'nested' => $reviewTypePermissions,
                    ],
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
