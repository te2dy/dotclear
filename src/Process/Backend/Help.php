<?php
/**
 * @since 2.27 Before as admin/help.php
 *
 * @package Dotclear
 * @subpackage Backend
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Backend;

use dcCore;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Process;
use Dotclear\Helper\Html\Html;

class Help extends Process
{
    public static function init(): bool
    {
        Page::check(dcCore::app()->auth->makePermissions([
            dcCore::app()->auth::PERMISSION_USAGE,
            dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
        ]));

        return self::status(true);
    }

    public static function render(): void
    {
        /**
         * $helpPage() get help content depending on context
         *
         * @param      mixed  ...$args  The arguments
         *
         * @return     array
         */
        $helpPage = function (...$args) {
            // Init return value
            $ret = [
                'content' => '',
                'title'   => '',
            ];

            if (empty($args)) {
                // No context given
                return $ret;
            }

            if (empty(dcCore::app()->resources['help'])) {
                // No available help
                return $ret;
            }

            $content = '';
            $title   = '';
            foreach ($args as $v) {
                if (is_object($v) && isset($v->content)) {
                    $content .= $v->content;

                    continue;
                }

                if (!isset(dcCore::app()->resources['help'][$v])) {
                    continue;
                }
                $f = dcCore::app()->resources['help'][$v];
                if (!file_exists($f) || !is_readable($f)) {
                    continue;
                }

                $fc = (string) file_get_contents($f);
                if (preg_match('|<body[^>]*?>(.*?)</body>|ms', $fc, $matches)) {
                    $content .= $matches[1];
                    if (preg_match('|<title[^>]*?>(.*?)</title>|ms', $fc, $matches)) {
                        $title = $matches[1];
                    }
                } else {
                    $content .= $fc;
                }
            }

            if (trim($content) == '') {
                return $ret;
            }

            $ret['content'] = $content;
            if ($title != '') {
                $ret['title'] = $title;
            }

            return $ret;
        };

        $help_page = !empty($_GET['page']) ? Html::escapeHTML($_GET['page']) : 'index';

        $content_array = $helpPage($help_page);

        if (($content_array['content'] === '') || ($help_page === 'index')) {
            $content_array = $helpPage('index');
        }

        if ($content_array['title'] !== '') {
            $breadcrumb = Page::breadcrumb(
                [
                    __('Global help')       => dcCore::app()->admin->url->get('admin.help'),
                    $content_array['title'] => '',
                ]
            );
        } else {
            $breadcrumb = Page::breadcrumb(
                [
                    __('Global help') => '',
                ]
            );
        }

        Page::open(
            __('Global help'),
            Page::jsPageTabs('first-step'),
            $breadcrumb
        );

        echo $content_array['content'];

        // Prevents global help link display
        dcCore::app()->resources['ctxhelp'] = true;

        Page::close();
    }
}
