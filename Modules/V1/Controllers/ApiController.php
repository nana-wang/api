<?php
/***
 * @author farnk
 * @comment5.0 api
 */
namespace DwComment\Modules\V1\Controllers;

use DwComment\Components\Factory\CommentOperate;
use DwComment\Exceptions\HttpException;
use DwComment\Models\Comment;
use Phalcon\Config;

class ApiController extends RestController
{

    public function __construct()
    {}

    public function post()
    {
        // Redis 链表数据
        // $_redis = new \DwComment\Library\RedisQueue();
        // $_redis->insertQueue(time());
        try {
            ;
            $Comment = new CommentOperate();
            $domain = $this->config['allowdomain']->domain;
            $do = $this->request->getPost('do');
            if (($Comment instanceof CommentOperate) && ! empty($do)) {
                if (is_object($Comment)) {
                    $obj = get_class_methods($Comment);
                    switch ($do) {
                        case self::INSERT_COMMENT_INFO:
                            $data = $Comment->insert_comment_info();
                            break;
                        case self::GET_COMMENT_INFO:
                            $data = $Comment->get_article_comment_info();
                            break;
                        case self::EDIT_COMMENT_INFO:
                            $data = $Comment->edit_comment_info();
                            break;
                        case self::DEL_COMMENT_INFO:
                            $data = $Comment->delete_comment_info();
                            break;
                        case self::SUPPORT_COMMENT_INFO:
                            $data = $Comment->support_comment_info();
                            break;
                        case self::UNSUPPORT_COMMENT_INFO:
                            $data = $Comment->unsupport_comment_info();
                            break;
                        case self::DISLIKE_COMMENT_INFO:
                            $data = $Comment->dislike_comment_info();
                            break;
                        case self::UNDISLIKE_COMMENT_INFO:
                            $data = $Comment->undislike_comment_info();
                            break;
                        case self::REPORT_COMMENT_INFO:
                            $data = $Comment->report_comment_info();
                            break;
                        case self::GET_REPORT_CATEGORY:
                            $data = $Comment->get_report_category();
                            break;
                        case self::GET_EMOTICON_INFO:
                            $data = $Comment->get_emoticon_info();
                            break;
                        case self::INSERT_USER_BLACKLIST:
                            $data = $Comment->insert_user_blacklist();
                            break;
                        case self::REMOVE_USER_BLACKLIST:
                            $data = $Comment->remove_user_blacklist();
                            break;
                        case self::GET_ARTICLE_COMPOSITE_SCORE:
                            $data = $Comment->get_article_composite_score();
                            break;
                        case self::GET_FOURM_CATEGORY_ITEM:
                            $data = $Comment->get_fourm_category_item();
                            break;
                        case self::GET_USER_INFO:
                            $data = $Comment->get_user_info();
                            break;
                        case self::TEMP_USER_INFO:
                            $data = $Comment->temp_user_info();
                            break;
                        case self::GET_PERSONAL_COMMENT_INFO:
                            $data = $Comment->get_personal_comment_info();
                            break;
                        case self::GET_QUEUE:
                            $data = $Comment->get_queue();
                            break;
                        case self::DEL_QUEUE:
                            $data = $Comment->del_queue();
                            break;
                        case self::READ_NOTIFY:
                            $data = $Comment->read_notify();
                            break;
                        case self::USER_NOTIFY_INFO:
                            $data = $Comment->user_notify_info();
                            break;
                        default:
                            break;
                    }
                    return $this->respond($data);
                }
            }
        } catch (\Exception $exception) {
            $send = new HttpException(self::error_code1_msg, 500, null, [
                'dev' => self::model_illegal,
                'internalCode' => self::error_code1,
                'more' => self::error_code1_msg
            ]);
            $send->send();
        }
    }
}