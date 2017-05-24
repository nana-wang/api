<?php
namespace DwComment\Models;
use Phalcon\Mvc\Model;

class Tread extends Model {

    public $id;

    public $comment_id;

    public $user_id;

    public $form_user_id;

    public $dateline;

    public $ip;

    /**
     * Returns the name of the table to use in the database
     *
     * @return string
     */
    public function getSource () {
        return "dw_comment_dislike";
    }
}