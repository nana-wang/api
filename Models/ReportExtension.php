<?php
namespace DwComment\Models;
use Phalcon\Mvc\Model;

class ReportExtension extends Model {

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
}