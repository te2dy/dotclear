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
use Dotclear\Helper\RestServer;
use Exception;

/**
 * Rest server handler.
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
        if (defined('DC_UPGRADE')) {
            try {
                if ($serve && file_exists(DC_UPGRADE)) {
                    // Remove watchdog file
                    unlink(DC_UPGRADE);
                } elseif (!$serve && !file_exists(DC_UPGRADE)) {
                    // Create watchdog file
                    touch(DC_UPGRADE);
                }
            } catch (Exception) {
            }
        }
    }

    public function serveRestRequests(): bool
    {
        return defined('DC_UPGRADE') && defined('DC_REST_SERVICES') && !file_exists(DC_UPGRADE) && DC_REST_SERVICES;
    }
}
