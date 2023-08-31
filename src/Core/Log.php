<?php
/**
 * Core log handler.
 *
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use dcAuth;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\TruncateStatement;
use Dotclear\Helper\Network\Http;
use Dotclear\Interface\Core\BehaviorInterface;
use Dotclear\Interface\Core\BlogLoaderInterface;
use Dotclear\Interface\Core\ConnectionInterface;
use Dotclear\Interface\Core\LogInterface;
use Exception;

class Log implements LogInterface
{
    public const LOG_TABLE_NAME = 'log';

    /** @var    string  Full log table name (including db prefix) */
    protected $log_table;

    /** @var    string  Full user table name (including db prefix) */
    protected $user_table;

    /**
     * Constructs a new instance.
     */
    public function __construct(
        private ConnectionInterface $con,
        private BehaviorInterface $behavior,
        private BlogLoaderInterface $blog_loader
    ) {
        $this->log_table  = $con->prefix() . self::LOG_TABLE_NAME;
        $this->user_table = $con->prefix() . dcAuth::USER_TABLE_NAME;
    }

    /**
     * Get log table name.
     *
     * @deprecated since 2.28, use self::LOG_TABLE_NAME instead
     *
     * @return  string  The log database table name
     */
    public function getTable(): string
    {
        return self::LOG_TABLE_NAME;
    }

    public function openCursor(): Cursor
    {
        return $this->con->openCursor($this->con->prefix() . self::LOG_TABLE_NAME);
    }

    public function getLogs(array $params = [], bool $count_only = false): MetaRecord
    {
        $sql = new SelectStatement();

        if ($count_only) {
            $sql->column($sql->count('log_id'));
        } else {
            $sql->columns([
                'L.log_id',
                'L.user_id',
                'L.log_table',
                'L.log_dt',
                'L.log_ip',
                'L.log_msg',
                'L.blog_id',
                'U.user_name',
                'U.user_firstname',
                'U.user_displayname',
                'U.user_url',
            ]);
        }

        $sql->from($sql->alias($this->log_table, 'L'));

        if (!$count_only) {
            $sql->join(
                (new JoinStatement())
                ->left()
                ->from($sql->alias($this->user_table, 'U'))
                ->on('U.user_id = L.user_id')
                ->statement()
            );
        }

        if (!empty($params['blog_id'])) {
            if ($params['blog_id'] === '*') {
                // Nothing to add here
            } else {
                $sql->where('L.blog_id = ' . $sql->quote($params['blog_id']));
            }
        } else {
            $sql->where('L.blog_id = ' . $sql->quote((string) $this->blog_loader->getBlog()?->id));
        }

        if (!empty($params['user_id'])) {
            $sql->and('L.user_id' . $sql->in($params['user_id']));
        }
        if (!empty($params['log_ip'])) {
            $sql->and('log_ip' . $sql->in($params['log_ip']));
        }
        if (!empty($params['log_table'])) {
            $sql->and('log_table' . $sql->in($params['log_table']));
        }

        if (!$count_only) {
            if (!empty($params['order'])) {
                $sql->order($sql->escape($params['order']));
            } else {
                $sql->order('log_dt DESC');
            }
        }

        if (!$count_only && !empty($params['limit'])) {
            $sql->limit($params['limit']);
        }

        $rs = $sql->select();
        $rs->extend('rsExtLog');

        return $rs;
    }

    public function addLog(Cursor $cur): int
    {
        $this->con->writeLock($this->log_table);

        try {
            # Get ID
            $sql = new SelectStatement();
            $sql
                ->column($sql->max('log_id'))
                ->from($this->log_table);

            $rs = $sql->select();

            $cur->log_id  = (int) $rs->f(0) + 1;
            $cur->blog_id = (string) $this->blog_loader->getBlog()?->id;
            $cur->log_dt  = date('Y-m-d H:i:s');

            $this->fillLogCursor($cur);

            # --BEHAVIOR-- coreBeforeLogCreate -- Log, Cursor
            $this->behavior->callBehavior('coreBeforeLogCreate', $this, $cur);

            $cur->insert();
            $this->con->unlock();
        } catch (Exception $e) {
            $this->con->unlock();

            throw $e;
        }

        # --BEHAVIOR-- coreAfterLogCreate -- Log, Cursor
        $this->behavior->callBehavior('coreAfterLogCreate', $this, $cur);

        return $cur->log_id;
    }

    /**
     * Fills the log Cursor.
     *
     * @param      Cursor   $cur     The current
     *
     * @throws     Exception
     */
    private function fillLogCursor(Cursor $cur)
    {
        if ($cur->log_msg === '') {
            throw new Exception(__('No log message'));
        }

        if ($cur->log_table === null) {
            $cur->log_table = 'none';
        }

        if ($cur->user_id === null) {
            $cur->user_id = 'unknown';
        }

        if ($cur->log_dt === '' || $cur->log_dt === null) {
            $cur->log_dt = date('Y-m-d H:i:s');
        }

        if ($cur->log_ip === null) {
            $cur->log_ip = Http::realIP();
        }
    }

    public function delLog(int $id): void
    {
        $sql = new DeleteStatement();
        $sql
            ->from($this->log_table)
            ->where('log_id = ' . $id)
            ->delete();
    }

    public function delLogs($id, bool $all = false): void
    {
        if ($all) {
            $this->delAllLogs();
        } elseif (is_int($id)) {
            $this->delLog($id);
        } else {
            $sql = new DeleteStatement();
            $sql
                ->from($this->log_table)
                ->where('log_id ' . $sql->in($id))
                ->delete();
        }
    }

    public function delAllLogs(): void
    {
        $sql = new TruncateStatement();
        $sql
            ->from($this->log_table)
            ->run();
    }
}
