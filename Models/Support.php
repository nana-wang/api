<?php
namespace DwComment\Models;
use Phalcon\Mvc\Model;

class Support extends \Phalcon\Mvc\Model {

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
        return "dw_comment_support";
    }
    
    // 评论点赞表更新
    public function upSupport ($data = []) {
        try {
            $this->db->begin();
            $this->save($data);
            if ($this->db->commit()) {return true;}
        } catch (\Exception $e) {
            return $e->getMessage('error') . $e->getLine();
        }
    }

    public function afterSave () {
        $this->dateline = time();
    }
}