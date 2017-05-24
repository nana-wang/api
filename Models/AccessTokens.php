<?php
namespace DwComment\Models;
use Phalcon\Mvc\Model;

class AccessTokens extends Model {

    public $userId;

    public $tokenId;

    public $isRevoked;

    public $expiry;

    /**
     * Returns the name of the table to use in the database
     *
     * @return string
     */
    public function getSource () {
        return "dw_access_tokens";
    }

    public function initialize () {
        $this->hasMany('userId', 'Users', 'id');
    }
}