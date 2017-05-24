<?php
namespace DwComment\Models;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Query;

class CommentExtension extends Model {

    public $id;

    public $author;

    public $title;

    public $year;

    /**
     * Returns the name of the table to use in the database
     *
     * @return string
     */
    public function getSource () {
        return "dw_comment_exp";
    }

    public function initialize () {
        $this->hasOne("id", "Comment", "id");
    }
}