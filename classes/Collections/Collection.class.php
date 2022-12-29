<?php
/**
 * Base Class to handle searching and retrieving objects.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Collections;
use glFusion\Database\Database;
use Doctrine\DBAL\Query\QueryBuilder;
use Shop\Log;
use Shop\Cache;
use Shop\Util\JSON;


/**
 * Class to handle searching and retrieving collections.
 * @package shop
 */
abstract class Collection
{
    /** Database object.
     * @var object */
    protected $_db = NULL;

    /** QueryBuilder object.
     * @var object */
    protected $_qb = NULL;

    /** Flag indicating that this is an administrator.
     * @var object */
    protected $isAdmin = false;

    /** Tags to add for caching.
     * @var array */
    protected $cache_tags = array('shop');

    /** Cache key constructed from SQL query and parameters.
     * @var string */
    private $_cache_key = '';


    public function __construct()
    {
        $this->_db = Database::getInstance();
        $this->_qb = $this->_db->conn->createQueryBuilder();
    }

    /**
     * Indicate that this is an administrator getting the collection.
     * Used to pass the isadmin flag to the entries.
     *
     * @param   boolean $admin  True if an administrator, False if not
     * @return  object  $this
     */
    public function setAdmin(bool $admin=true) : self
    {
        $this->isAdmin = $admin;
        return $this;
    }


    /**
     * Generic function to add a `where` clause.
     *
     * @see     self::setParameter()
     * @param   string  $sql    SQL to add
     * @return  object  $this
     */
    public function andWhere(string $sql) : self
    {
        $this->_qb->andWhere($sql);
        return $this;
    }


    /**
     * Generic function to add a parameter value for `andWhere` if needed.
     *
     * @see     self::andWhere()
     * @param   string  $id     Parameter ID name used in andWhere()
     * @param   mixed   $value  Value to set
     * @param   integer $type   Type of parameter
     * @return  object  $this
     */
    public function setParameter(string $id, $value, int $type=Database::STRING) : self
    {
        $this->_qb->setParameter($id, $value, $type);
        return $this;
    }


    /**
     * Set the limit for the result set.
     *
     * @param   integer $start  Starting record, or max if $max is empty
     * @param   integer $max    Max records, optional
     * @return  object  $this
     */
    public function withLimit(int $start, ?int $max=NULL) : self
    {
        if (is_null($max)) {
            $this->_qb->setFirstResult(0)
                      ->setMaxResults($start);
        } else {
            $this->_qb->setFirstResult($start)
                      ->setMaxResults($max);
        }
        return $this;
    }


    /**
     * Add an orderby clause.
     *
     * @param   string  $fld    Field to use for ordering
     * @param   string  $dir    Direction, ASC by default
     * @return  object  $this
     */
    public function orderBy(string $fld, string $dir='ASC') : self
    {
        $dir = strtoupper($dir);
        $dir = $dir == 'ASC' ? 'ASC' : 'DESC';
        $this->_qb->addOrderBy(Database::getInstance()->conn->quoteIdentifier($fld), $dir);
        return $this;
    }


    /**
     * Use this to order by RAND().
     *
     * @return  object  $this
     */
    public function withRandom() : self
    {
        $this->_qb->addOrderBy('RAND()');
        return $this;
    }


    /**
     * Return the QueryBuilder object for further query customization.
     *
     * @return  object      QueryBuilder object
     */
    public function getQueryBuilder() : QueryBuilder
    {
        return $this->_qb;
    }


    /**
     * Try to get data from the cache. First constructs the cache key.
     *
     * @param   string  $key    Additional key to prepend
     * @return  array|null  Array of data, NULL if not found
     */
    protected function tryCache(string $key) : ?array
    {
        $this->_cache_key = md5($key . $this->_qb->getSQL() . JSON::encode($this->_qb->getParameters()));
        return Cache::get($this->_cache_key);
    }


    /**
     * Set a data array into the cache. Key is created by tryCache().
     *
     * @param   array   $data   Data to set
     * @param   array   $tags   Tags to apply
     */
    protected function setCache(array $data, array $tags) : void
    {
        Cache::set($this->_cache_key, $data, $tags);
    }


    /**
     * Execute the query.
     *
     * @return  object|false    Statement object, or false on error
     */
    public function execute()
    {
        try {
            $stmt = $this->_qb->execute();
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        return $stmt;
    }


    /**
     * Get the count of rows that would be returned.
     *
     * @return  integer     Row count
     */
    public function getCount() : int
    {
        $stmt = $this->execute();
        if ($stmt) {
            return $stmt->rowCount();
        } else {
            return 0;
        }
    }


    /**
     * Get the raw records for the events.
     *
     * @return  array       Array of DB rows
     */
    public function getRows() : array
    {
        $rows = $this->tryCache('rows');
        if (is_array($rows)) {
            return $rows;
        }

        $rows = array();
        $stmt = $this->execute();
        if (!$stmt || $stmt->rowCount() < 1) {
            return $rows;
        }

        $rows = $stmt->fetchAllAssociative();
        $this->setCache($rows, $this->cache_tags);
        return $rows;
    }


    /**
     * Add additional cache tags to the collection.
     *
     * @param   array   $tags   Array of tag names
     * @return  object  $this
     */
    public function addCacheTags(array $tags) : self
    {
        $this->cache_tags = array_merge($this->cache_tags, $tags);
        return $this;
    }

}

