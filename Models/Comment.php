<?php
namespace DwComment\Models;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Query;
use Phalcon\DI;
use Phalcon\Mvc\Model as PhalconModel;
use Phalcon\Mvc\Model\Query\Builder;
use Swoole\IFace\Protocol;

class Comment extends \Phalcon\Mvc\Model {

    public $id;

    public $comment_user_id;

    public $comment_to_user_id;

    public $comment_user_nickname;

    public $comment_title;

    public $comment_url;

    public $comment_parent_id;

    public $comment_up;

    public $comment_down;

    public $comment_channel_area;

    public $comment_user_type;

    public $comment_created_at;

    public $comment_updated_at;

    public $comment_examine_at;

    public $comment_status;

    public $comment_device;

    public $comment_is_lock;

    public $comment_is_hide;

    public $comment_is_report;

    public $comment_ip;

    /**
     * Returns the name of the table to use in the database
     *
     * @return string
     */
    public function getSource () {
        return "dw_comment";
    }

    public function getId () {
        return $this->id;
    }

    public function getName () {
        return $this->name;
    }

    public function setName ($name) {
        $this->name = $name;
    }

    /**
     * Insert value for created and updated at column
     */
    public function beforeSave () {
        $this->created_at = time();
        $this->updated_at = time();
    }

    /**
     * Update value for updated at column
     */
    public function beforeUpdate () {
        $this->updated_at = time();
    }

    public function initialize () {
        $this->hasOne("id", "CommentExtension", "id");
    }
}