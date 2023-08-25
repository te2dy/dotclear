<?php
/**
 * @brief dcLegacyEditor, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\dcLegacyEditor;

use dcCore;
use Dotclear\Core\Backend\Menus;
use Dotclear\Core\Process;
use Dotclear\Helper\Html\WikiToHtml;

class Backend extends Process
{
    public static function init(): bool
    {
        // Dead but useful code (for l10n)
        __('dcLegacyEditor') . __('dotclear legacy editor');

        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        My::addBackendMenuItem(Menus::MENU_PLUGINS, [], '');

        if (My::settings()->active) {
            if (!(dcCore::app()->wiki instanceof WikiToHtml)) {
                dcCore::app()->initWikiPost();
            }

            dcCore::app()->addEditorFormater('dcLegacyEditor', 'xhtml', fn ($s) => $s);
            dcCore::app()->addFormaterName('xhtml', __('HTML'));

            dcCore::app()->addEditorFormater('dcLegacyEditor', 'wiki', [dcCore::app()->wiki, 'transform']);
            dcCore::app()->addFormaterName('wiki', __('Dotclear wiki'));

            dcCore::app()->behavior->addBehaviors([
                'adminPostEditor' => [BackendBehaviors::class, 'adminPostEditor'],
                'adminPopupMedia' => [BackendBehaviors::class, 'adminPopupMedia'],
                'adminPopupLink'  => [BackendBehaviors::class, 'adminPopupLink'],
                'adminPopupPosts' => [BackendBehaviors::class, 'adminPopupPosts'],
            ]);

            // Register REST methods
            dcCore::app()->rest->addFunction('wikiConvert', [Rest::class, 'convert']);
        }

        return true;
    }
}
