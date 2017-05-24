<?php
namespace DwComment\Models;
use Phalcon\Mvc\Model;

class Report extends Model {

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
        return "dw_report";
    }

    public function initialize () {
        $this->belongsTo("id", "ReportExtension", "id");
    }
}