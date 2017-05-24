<?php
namespace DwComment\Models;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Query;

class Blacklist extends Model {


    /**
     * Returns the name of the table to use in the database
     *
     * @return string
     */
    public function getSource () {
        return "dw_blacklist";
    }

    public function initialize () {
        //$this->hasOne("id", "CommentExtension", "id");
    }

    /**
     * Insert value for created and updated at column
     */
    public function beforeSave () {
    }

    /**
     * Update value for updated at column
     */
    public function beforeUpdate () {
    }
}