<?php
namespace DwComment\Models;
use Phalcon\Mvc\Model;

class NotifyCategory extends \Phalcon\Mvc\Model {

    public $id;

    public $name;

    public $title;

    public $content;

    /**
     * Returns the name of the table to use in the database
     *
     * @return string
     */

    public function getSource () {
        return "dw_notify_category";
    }


    public function afterSave () {
        $this->dateline = time();
    }
}