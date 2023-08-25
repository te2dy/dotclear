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
use Dotclear\Helper\Html\WikiToHtml;

if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

class Rest
{
    /**
     * Convert wiki to HTML REST service (JSON)
     *
     * @param      array   $get    The get
     * @param      array   $post   The post
     *
     * @return     array
     */
    public static function convert(array $get, array $post): array
    {
        $wiki = $post['wiki'] ?? '';
        $ret  = false;
        $html = '';
        if ($wiki !== '') {
            if (!(dcCore::app()->wiki instanceof WikiToHtml)) {
                dcCore::app()->initWikiPost();
            }
            $html = dcCore::app()->formater->callEditorFormater(My::id(), 'wiki', $wiki);
            $ret  = strlen($html) > 0;

            if ($ret) {
                $media_root = dcCore::app()->blog->host;
                $html       = preg_replace_callback('/src="([^\"]*)"/', function ($matches) use ($media_root) {
                    if (!preg_match('/^http(s)?:\/\//', $matches[1])) {
                        // Relative URL, convert to absolute
                        return 'src="' . $media_root . $matches[1] . '"';
                    }
                    // Absolute URL, do nothing
                    return $matches[0];
                }, $html);
            }
        }

        return [
            'ret' => $ret,
            'msg' => $html,
        ];
    }
}
