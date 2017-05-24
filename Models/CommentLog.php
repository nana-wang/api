<?php
namespace DwComment\Models;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Query;

class CommentLog extends Model {


    /**
     * Returns the name of the table to use in the database
     *
     * @return string
     */
    public function getSource () {
        return "dw_comment_log";
    }

}