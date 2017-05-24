<?php
use Phalcon\Mvc\Model\Query;

// Instantiate the Query
$query = new Query("SELECT * FROM dw_comment limit 10", $this->getDI());
