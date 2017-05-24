<?php
namespace DwComment\Library;

use DwComment\Modules\V1\Controllers\RestController;

/**
 *
 * @author Frank
 *         主评论链表操作
 *         @2017-02-25
 */
class RedisQueue extends RestController
{

    public $redisHandler;
    // 队列保存键名
    const COMMENT_LIST_KEY = 'comment_main_lists';
    // 队列长度保存键名
    const COMMENT_LIST_LEN = 'comment_main_lists_length';
    // 队列长度保存键名缓存时间
    const COMMENT_LIST_LEN_CACHE_TIME = 3600;
    // 主评上限 按时间权重
    const COMMENT_MAIN_LIST_LEN = 50;
    // 主评上限 按时间权重 按顶的次数排序
    const COMMENT_MAIN_LIST_SUPPORT = 50;
    // 主评论Key
    const CONTENT_MASTER_KEY = 'content_master_key_';
    // 子评论Key
    const CONTENT_SON_KEY = 'content_son_key_';

    protected $key;

    protected $id;

    public $content_master_key_data;

    function __construct()
    {
        parent::__construct();
        $this->redisHandler = $this->redis;
        $this->redisHandler->ltrim(self::COMMENT_LIST_KEY, 0, self::COMMENT_MAIN_LIST_LEN - 1);
    }
    // 评论最新评论数据
    public function getCommentTop($n = null)
    {
        if (! empty($n)) {
            $this->redisHandle->lrange(self::COMMENT_LIST_KEY, 0, $n - 1);
        } else {
            $this->redisHandle->lrange(self::COMMENT_LIST_KEY, 0, self::COMMENT_MAIN_LIST_LEN - 1);
        }
    }
    // 主贴最新 Key
    public function setContentMasterKey($key, $id)
    {
        if ($this->redisHandler->exists(self::CONTENT_MASTER_KEY . $key)) {
            $this->redisHandler->ltrim(self::CONTENT_MASTER_KEY . $key, 0, self::COMMENT_MAIN_LIST_LEN - 1);
        }
        $length = $this->redisHandler->lpush(self::CONTENT_MASTER_KEY . $key, $id);
        if ($length) {
            $this->setInc(1);
        }
        return $length;
    }
    // 检测主题是否有评论
    public function checkContentMasterKey($key)
    {
        if ($this->redisHandler->exists(self::CONTENT_MASTER_KEY . $key)) {return true;}
        return false;
    }
    
    // 子贴数据 Key
    public function setContentSonKey($key, $id)
    {
        if ($this->redisHandler->exists(self::CONTENT_SON_KEY . $key)) {
            $this->redisHandler->ltrim(self::CONTENT_SON_KEY . $key, 0, self::COMMENT_MAIN_LIST_LEN - 1);
        }
        $length = $this->redisHandler->lpush(self::CONTENT_SON_KEY . $key, $id);
        if ($length) {
            $this->setInc(1);
        }
        return $length;
    }
    // 主-子-数据
    public function setContentDetail($id, $data)
    {
        // 24小时
        $length = $this->redisHandler->set($id, serialize($data));
        $this->redisHandler->expire($id, 86400);
    }
    // 主题master key
    public function getContentMasterKey($key)
    {
        $data = $this->redisHandler->lrange(self::CONTENT_MASTER_KEY . $key, 0, - 1);
        return $data;
    }

    /**
     * [getLength 获取队列长度]
     *
     * @return [type] [description]
     */
    public function getLength()
    {
        $length = (int) $this->redisHandler->get(self::COMMENT_LIST_LEN);
        if (! empty($length)) {
            $length = $this->redisHandler->lSize(self::COMMENT_LIST_KEY);
            $this->redisHandler->setex(self::COMMENT_LIST_LEN, self::COMMENT_LIST_LEN_CACHE_TIME, $length);
        }
        return $length ? $length : 0;
    }

    /**
     * [insertQueue 插入队列]
     *
     * @param [type] $data
     *            [description]
     * @return [type] [description]
     */
    public function insertQueue($data)
    {
        $length = $this->redisHandler->lpush(self::COMMENT_LIST_KEY, 
            // serialize($data));
            $data);
        if ($length) {
            $this->setInc(1);
        }
        return $length;
    }

    /**
     * [removeQueue 从队列中取出数据]
     *
     * @return [type] [description]
     */
    public function removeQueue()
    {
        $value = $this->redisHandler->rPop(self::COMMENT_LIST_KEY);
        if ($value) {
            $this->setDec(1);
            return unserialize($value);
        } else {
            return false;
        }
    }
    // 读取链表数据
    public function lrangeQueue()
    {
        $list = $this->redisHandler->lrange(self::COMMENT_LIST_KEY, 0, - 1);
        return $list;
    }

    /**
     * [setInc 队列长度增加]
     *
     * @param integer $value
     *            [description]
     */
    public function setInc($value = 1)
    {
        $list_len = $this->getLength() + $value;
        return $this->redisHandler->setex(self::COMMENT_LIST_LEN, self::COMMENT_LIST_LEN_CACHE_TIME, $list_len);
    }

    /**
     * [setDec 队列长度减少]
     *
     * @param integer $value
     *            [description]
     */
    public function setDec($value = 1)
    {
        $list_len = $this->getLength() - $value;
        return $this->redisHandler->setex(self::COMMENT_LIST_LEN, self::COMMENT_LIST_LEN_CACHE_TIME, $list_len);
    }
}