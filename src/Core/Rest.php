<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use dcCore;
use Dotclear\App;
use Dotclear\Helper\RestServer;
use Exception;

/**
 * @brief   Rest server handler.
 *
 * This class extends Dotclear\Helper\RestServer to handle dcCore instance in each rest method call (XML response only).
 * Instance of this class is provided by App::rest().
 *
 * Rest class uses RestServer (class that RestInterface interface) constants.
 */
class Rest extends RestServer
{
    /**
     * @todo    Remove old dcCore from RestServer::serve returned parent parameters
     */
    public function serve(string $encoding = 'UTF-8', int $format = parent::XML_RESPONSE, $param = null): bool
    {
        if (isset($_REQUEST['json'])) {
            // No need to use dcCore::app() with JSON response
            return parent::serve($encoding, parent::JSON_RESPONSE);
        }

        // Use dcCore::app() as supplemental parameter to ensure retro-compatibility
        return parent::serve($encoding, parent::XML_RESPONSE, dcCore::app());
    }

    public function enableRestServer(bool $serve = true): void
    {
        if (App::config()->coreUpgrade() != '') {
            try {
                if ($serve && file_exists(App::config()->coreUpgrade())) {
                    // Remove watchdog file
                    unlink(App::config()->coreUpgrade());
                } elseif (!$serve && !file_exists(App::config()->coreUpgrade())) {
                    // Create watchdog file
                    touch(App::config()->coreUpgrade());
                }
            } catch (Exception) {
            }
        }
    }

    public function serveRestRequests(): bool
    {
        return !file_exists(App::config()->coreUpgrade()) && App::config()->allowRestServices();
    }
}
