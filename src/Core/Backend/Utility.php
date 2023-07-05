<?php
/**
 * @package Dotclear
 * @subpackage Backend
 *
 * Utility class for admin context.
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Backend;

use dcCore;
use dcNotices;
use dcTraitDynamicProperties;
use Dotclear\Core\Process;
use Dotclear\Fault;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Network\Http;
use Exception;

class Utility extends Process
{
    use dcTraitDynamicProperties;

    /**
     * Current admin page URL
     *
     * @var string
     */
    protected $p_url;

    /** @var    Url     Backend (admin) Url handler instance */
    public Url $url;

    /** @var    Favorites   Backend (admin) Favorites handler instance */
    public Favorites $favs;

    /** @var    Menus   Backend (admin) Menus handler instance */
    public Menus $menu;

    // Constants

    /** @deprecated since 2.27, use Menus::MENU_XXX */
    public const MENU_FAVORITES = Menus::MENU_FAVORITES;
    public const MENU_BLOG      = Menus::MENU_BLOG;
    public const MENU_SYSTEM    = Menus::MENU_SYSTEM;
    public const MENU_PLUGINS   = Menus::MENU_PLUGINS;

    /**
     * Constructs a new instance.
     *
     * @throws     Exception  (if not admin context)
     */
    public function __construct()
    {
        if (!defined('DC_CONTEXT_ADMIN')) {
            throw new Exception('Application is not in administrative context.', 500);
        }

        // HTTP/1.1
        header('Expires: Mon, 13 Aug 2003 07:48:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    }

    /**
     * Prepare the context.
     *
     * @return     bool
     */
    public static function init(): bool
    {
        define('DC_CONTEXT_ADMIN', true);

        return true;
    }

    /**
     * Instanciate this as a singleton and initializes the context.
     */
    public static function process(): bool
    {
        // nullsafe php 7.4
        if (is_null(dcCore::app()->auth)) {
            throw new Exception('Application is not in administrative context.', 500);
        }

        if (!(dcCore::app()->admin instanceof self)) {
            dcCore::app()->admin = new self();
        }

        // New admin url instance
        dcCore::app()->admin->url = new Url();
        dcCore::app()->admin->url->register('admin.auth', 'Auth');

        /* @deprecated since 2.27, use dcCore::app()->admin->url instead */
        dcCore::app()->adminurl = dcCore::app()->admin->url;

        if (dcCore::app()->auth->sessionExists()) {
            # If we have a session we launch it now
            try {
                if (!dcCore::app()->auth->checkSession()) {
                    # Avoid loop caused by old cookie
                    $p    = dcCore::app()->session->getCookieParameters(false, -600);
                    $p[3] = '/';
                    setcookie(...$p);

                    // Preserve safe_mode if necessary
                    $params = !empty($_REQUEST['safe_mode']) ? ['safe_mode' => 1] : [];
                    dcCore::app()->admin->url->redirect('admin.auth', $params);
                }
            } catch (Exception $e) {
                new Fault(__('Database error'), __('There seems to be no Session table in your database. Is Dotclear completly installed?'), Fault::DATABASE_ISSUE);
            }

            # Fake process to logout (kill session) and return to auth page.
            dcCore::app()->admin->url->register('admin.logout', 'Logout');
            if (!empty($_REQUEST['process']) && $_REQUEST['process'] == 'Logout') {
                // Enable REST service if disabled, for next requests
                if (!dcCore::app()->serveRestRequests()) {
                    dcCore::app()->enableRestServer(true);
                }
                // Kill admin session
                dcCore::app()->killAdminSession();
                // Logout
                dcCore::app()->admin->url->redirect('admin.auth');
                exit;
            }

            # Check nonce from POST requests
            if (!empty($_POST) && (empty($_POST['xd_check']) || !dcCore::app()->checkNonce($_POST['xd_check']))) {
                new Fault('Precondition Failed', __('Precondition Failed'), 412);
            }

            if (!empty($_REQUEST['switchblog']) && dcCore::app()->auth->getPermissions($_REQUEST['switchblog']) !== false) {
                $_SESSION['sess_blog_id'] = $_REQUEST['switchblog'];

                if (!empty($_REQUEST['redir'])) {
                    # Keep context as far as possible
                    $redir = (string) $_REQUEST['redir'];
                } else {
                    # Removing switchblog from URL
                    $redir = (string) $_SERVER['REQUEST_URI'];
                    $redir = (string) preg_replace('/switchblog=(.*?)(&|$)/', '', $redir);
                    $redir = (string) preg_replace('/\?$/', '', $redir);
                }

                dcCore::app()->auth->user_prefs->interface->drop('media_manager_dir');

                if (!empty($_REQUEST['process']) && $_REQUEST['process'] == 'Media' || strstr($redir, 'media.php') !== false) {
                    // Remove current media dir from media manager URL
                    $redir = (string) preg_replace('/d=(.*?)(&|$)/', '', $redir);
                }

                Http::redirect($redir);
                exit;
            }

            # Check blog to use and log out if no result
            if (isset($_SESSION['sess_blog_id'])) {
                if (dcCore::app()->auth->getPermissions($_SESSION['sess_blog_id']) === false) {
                    unset($_SESSION['sess_blog_id']);
                }
            } else {
                if (($b = dcCore::app()->auth->findUserBlog(dcCore::app()->auth->getInfo('user_default_blog'), false)) !== false) {
                    $_SESSION['sess_blog_id'] = $b;
                    unset($b);
                }
            }

            # Loading locales
            Helper::loadLocales();
            /*
             * @var        string
             *
             * @deprecated Since 2.23, use dcCore::app()->lang instead
             */
            $GLOBALS['_lang'] = &dcCore::app()->lang;

            if (isset($_SESSION['sess_blog_id'])) {
                dcCore::app()->setBlog($_SESSION['sess_blog_id']);
            } else {
                dcCore::app()->session->destroy();
                dcCore::app()->admin->url->redirect('admin.auth');
            }
        }

        dcCore::app()->admin->url->register('admin.posts', 'Posts');
        dcCore::app()->admin->url->register('admin.popup_posts', 'PostsPopup'); //use admin.posts.popup
        dcCore::app()->admin->url->register('admin.posts.popup', 'PostsPopup');
        dcCore::app()->admin->url->register('admin.post', 'Post');
        dcCore::app()->admin->url->register('admin.post.media', 'PostMedia');
        dcCore::app()->admin->url->register('admin.blog.theme', 'BlogTheme');
        dcCore::app()->admin->url->register('admin.blog.pref', 'BlogPref');
        dcCore::app()->admin->url->register('admin.blog.del', 'BlogDel');
        dcCore::app()->admin->url->register('admin.blog', 'Blog');
        dcCore::app()->admin->url->register('admin.blogs', 'Blogs');
        dcCore::app()->admin->url->register('admin.categories', 'Categories');
        dcCore::app()->admin->url->register('admin.category', 'Category');
        dcCore::app()->admin->url->register('admin.comments', 'Comments');
        dcCore::app()->admin->url->register('admin.comment', 'Comment');
        dcCore::app()->admin->url->register('admin.help', 'Help');
        dcCore::app()->admin->url->register('admin.help.charte', 'HelpCharte');
        dcCore::app()->admin->url->register('admin.home', 'Home');
        dcCore::app()->admin->url->register('admin.langs', 'Langs');
        dcCore::app()->admin->url->register('admin.link.popup', 'LinkPopup');
        dcCore::app()->admin->url->register('admin.media', 'Media');
        dcCore::app()->admin->url->register('admin.media.item', 'MediaItem');
        dcCore::app()->admin->url->register('admin.plugins', 'Plugins');
        dcCore::app()->admin->url->register('admin.plugin', 'Plugin');
        dcCore::app()->admin->url->register('admin.search', 'Search');
        dcCore::app()->admin->url->register('admin.user.preferences', 'UserPreferences');
        dcCore::app()->admin->url->register('admin.user', 'User');
        dcCore::app()->admin->url->register('admin.user.actions', 'UsersActions');
        dcCore::app()->admin->url->register('admin.users', 'Users');
        dcCore::app()->admin->url->register('admin.help', 'Help');
        dcCore::app()->admin->url->register('admin.update', 'Update');
        dcCore::app()->admin->url->register('admin.csp.report', 'CspReport');
        dcCore::app()->admin->url->register('admin.rest', 'Rest');

        // we don't care of admin process for FileServer
        dcCore::app()->admin->url->register('load.plugin.file', 'index.php', ['pf' => 'dummy.css']);
        dcCore::app()->admin->url->register('load.var.file', 'index.php', ['vf' => 'dummy.json']);

        // (re)set post type with real backend URL (as admin URL handler is known yet)
        dcCore::app()->setPostType('post', urldecode(dcCore::app()->admin->url->get('admin.post', ['id' => '%d'], '&')), dcCore::app()->url->getURLFor('post', '%s'), 'Posts');

        // No user nor blog, do not load more stuff
        if (is_null(dcCore::app()->auth) || !(dcCore::app()->auth->userID() && dcCore::app()->blog !== null)) {
            return true;
        }

        # Loading resources and help files
        require DC_L10N_ROOT . '/en/resources.php';
        if ($f = L10n::getFilePath(DC_L10N_ROOT, '/resources.php', dcCore::app()->lang)) {
            require $f;
        }
        unset($f);

        if (($hfiles = @scandir(DC_L10N_ROOT . '/' . dcCore::app()->lang . '/help')) !== false) {
            foreach ($hfiles as $hfile) {
                if (preg_match('/^(.*)\.html$/', $hfile, $m)) {
                    dcCore::app()->resources['help'][$m[1]] = DC_L10N_ROOT . '/' . dcCore::app()->lang . '/help/' . $hfile;
                }
            }
        }
        unset($hfiles);
        // Contextual help flag
        dcCore::app()->resources['ctxhelp'] = false;

        $user_ui_nofavmenu = dcCore::app()->auth->user_prefs->interface->nofavmenu;

        dcCore::app()->notices     = new dcNotices();
        dcCore::app()->admin->favs = new Favorites();
        dcCore::app()->admin->menu = new Menus();

        /* @deprecated since 2.27, use dcCore::app()->admin->favs instead */
        dcCore::app()->favs = dcCore::app()->admin->favs;

        /* @deprecated since 2.27, use dcCore::app()->admin->menu instead */
        dcCore::app()->menu = dcCore::app()->admin->menu;

        /* @deprecated Since 2.23, use dcCore::app()->admin->menu instead */
        $GLOBALS['_menu'] = dcCore::app()->admin->menu;

        // Set default menu
        dcCore::app()->admin->menu->setDefaultItems();

        if (!$user_ui_nofavmenu) {
            dcCore::app()->admin->favs->appendMenuSection(dcCore::app()->admin->menu);
        }

        # Loading plugins
        dcCore::app()->plugins->loadModules(DC_PLUGINS_ROOT, 'admin', dcCore::app()->lang);
        dcCore::app()->admin->favs->setup();

        if (!$user_ui_nofavmenu) {
            dcCore::app()->admin->favs->appendMenu(dcCore::app()->admin->menu);
        }

        if (empty(dcCore::app()->blog->settings->system->jquery_migrate_mute)) {
            dcCore::app()->blog->settings->system->put('jquery_migrate_mute', true, 'boolean', 'Mute warnings for jquery migrate plugin ?', false);
        }
        if (empty(dcCore::app()->blog->settings->system->jquery_allow_old_version)) {
            dcCore::app()->blog->settings->system->put('jquery_allow_old_version', false, 'boolean', 'Allow older version of jQuery', false, true);
        }

        # Admin behaviors
        dcCore::app()->addBehavior('adminPopupPosts', [BlogPref::class, 'adminPopupPosts']);

        return true;
    }

    /**
     * Sets the admin page URL.
     *
     * @param      string  $value  The value
     */
    public function setPageURL(string $value): void
    {
        $this->p_url = $value;

        /*
         * @deprecated since 2.24, may be removed in near future
         *
         * @var string
         */
        $GLOBALS['p_url'] = $value;
    }

    /**
     * Gets the admin page URL.
     *
     * @return     string   The URL.
     */
    public function getPageURL(): string
    {
        return (string) $this->p_url;
    }
}
