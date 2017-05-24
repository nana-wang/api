<?php
namespace DwComment\Modules\V1\Controllers;

use DwComment\Models\Comment;
use Qiniu\json_decode;
use DwComment\Models\CommentScore;
use Phalcon\Mvc\Model\Query\Builder as QueryBuilder;
use DwComment\Models\CommentExtension;
use Swoole\Console;

/**
 * **
 * @评论列表数据
 *
 * @author
 *
 */
class FrontendController extends RestController
{

    private $content_master_key_data;
    // 文字类型
    const word_types = '1';
    // 评论数点赞
    private function get_user_info($temp_user_id = null)
    {
        $temp_user_id = trim($temp_user_id);
        $temp_user_array = array(
            '1' => array(
                'username' => '飞天侠客',
                'userimg' => 'http://demo.dwnews.com/api/images/tx_ico.png'
            ),
            '2' => array(
                'username' => '小龙女',
                'userimg' => 'http://demo.dwnews.com/api/images/tx_ico.png'
            ),
            '3' => array(
                'username' => '特朗普',
                'userimg' => 'http://demo.dwnews.com/api/images/tx_ico.png'
            ),
            '4' => array(
                'username' => '小布什',
                'userimg' => 'http://demo.dwnews.com/api/images/tx_ico.png'
            ),
            '5' => array(
                'username' => '本拉登',
                'userimg' => 'http://demo.dwnews.com/api/images/tx_ico.png'
            ),
            '6' => array(
                'username' => '普京',
                'userimg' => 'http://demo.dwnews.com/api/images/tx_ico.png'
            ),
            'DW_5008' => array(
                'username' => 'sunday',
                'userimg' => 'http://demo.dwnews.com/api/images/tx_ico.png'
            )
        );
        
        if (! empty($temp_user_id) && isset($temp_user_array[$temp_user_id])) {
            $user['username'] = $temp_user_array[$temp_user_id]['username'];
            $user['userimg'] = $temp_user_array[$temp_user_id]['userimg'];
        } else {
            $user['username'] = '匿名用户';
            $user['userimg'] = 'http://demo.dwnews.com/api/images/txbg_ico.png';
        }
        return $user;
    }

    private function arraySequence($array, $field, $sort = 'SORT_DESC')
    {
        $arrSort = array();
        foreach ($array as $uniqid => $row) {
            foreach ($row as $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        array_multisort($arrSort[$field], constant($sort), $array);
        return $array;
    }

    /**
     * 测试接口
     */
    public function comment($page = null, $showsum = null)
    {
        try {
            $CommentUrl = $key = $url = $_REQUEST['key'];
            $user_id = $_REQUEST['user_id'];
            $page = $_REQUEST['p'] ? $_REQUEST['p'] : 0;
            $showsum = $_REQUEST['sum'] ? $_REQUEST['sum'] : 10;
            if ($user_id != 'dw_999999' || ! empty($user_id)) {
                $condition['user_id'] = " and main.comment_user_id ='{$user_id}'";
            }
            $_redis = new \DwComment\Library\RedisQueue();
            // 如果Redis 没有评论取最新50条主评论
            if (! $_redis->checkContentMasterKey($key)) {
                $_phql = 'SELECT Comment.id FROM \DwComment\Models\Comment as Comment where Comment.comment_url = :comment_url: and Comment.comment_parent_id = :comment_parent_id:  order by Comment.id DESC limit 50';
                $master_list_data = $this->modelsManager->executeQuery($_phql, [
                    'comment_url' => $CommentUrl,
                    'comment_parent_id' => 0
                ])->toArray();
                if (is_array($master_list_data)) {
                    $_arrayData = $this->arraySequence($master_list_data, 'id', 'SORT_ASC');
                    foreach ($_arrayData as $k => $value) {
                        $_redis->setContentMasterKey($CommentUrl, $value['id']);
                        $master_k[] = $value['id'];
                    }
                }
            } else {
                $master_k = $this->content_master_key_data = $_redis->getContentMasterKey($key);
            }
            $master_k = implode(',', $master_k);
            if ($page < 1) {
                $parentID = ' and Comment.id in (' . $master_k . ')';
            } else {
                $parentID = ' and Comment.comment_parent_id = 0 ';
            }
            // 评论Comment Model
            $Comment = '\DwComment\Models\Comment as Comment';
            // 评论CommentExtension Model
            $CommentExtension = '\DwComment\Models\CommentExtension as CommentExtension';
            $phql = 'SELECT Comment.id,Comment.comment_user_id,Comment.comment_to_user_id,Comment.comment_user_nickname,Comment.comment_title,Comment.comment_url,Comment.comment_parent_id,Comment.comment_up,Comment.comment_down,Comment.comment_channel_area,Comment.comment_user_type,Comment.comment_created_at,Comment.comment_updated_at,Comment.comment_examine_at,Comment.comment_status,Comment.comment_device,Comment.comment_is_lock,Comment.comment_is_hide,Comment.comment_is_report,Comment.comment_ip,CommentExtension.comment_content,CommentExtension.comment_attachment FROM ' . $Comment . ' left join ' . $CommentExtension . ' on Comment.id=CommentExtension.id where (Comment.comment_status in(1,6) or Comment.comment_status in(4,5)) and Comment.comment_url = :comment_url: ' . $parentID . ' order by Comment.id DESC limit ' . $page . ' , ' . $showsum;
            $data = $this->modelsManager->executeQuery($phql, [
                'comment_url' => $CommentUrl
            ])->toArray();
            // 主评论数据
            if (isset($data)) {
                $zhu_ping_id = $zhu_ping_uid = '';
                foreach ($data as $key1 => $value1) {
                    $zhu_ping .= ',' . $value1['id'];
                    $zhu_ping_uid .= ',' . $value1['comment_user_id'];
                }
                foreach ($data as $key => &$value) {
                    $only_root_data[$key] = $value;
                    $only_root_data[$key]['comment_created_at'] = $this->formDate($value['comment_created_at']);
                    // 评论顶
                    $zan_main = $this->comment_zan($value['id'], $user_id);
                    $only_root_data[$key]['support'] = $zan_main['support'];
                    $only_root_data[$key]['support_user_flg'] = $zan_main['support_user_flg'];
                    // 评论踩
                    $zan_main = $this->comment_cai($value['id'], $user_id);
                    $only_root_data[$key]['dislike'] = $zan_main['dislike'];
                    $only_root_data[$key]['dislike_user_flg'] = $zan_main['dislike_user_flg'];
                    // 用户信息TODO(请求接口)
                    $user_info = $this->get_user_info($value['comment_user_id']);
                    $only_root_data[$key]['username'] = $user_info['username'];
                    $only_root_data[$key]['userimg'] = $user_info['userimg'];
                    // 用户信息TODO(请求接口)
                    $user_info = $this->get_user_info($value['comment_to_user_id']);
                    $only_root_data[$key]['comment_to_username'] = $user_info['username'];
                    $only_root_data[$key]['comment_to_userimg'] = $user_info['userimg'];
                    $_temp = $this->getSubData($value['id']);
                    $reply_id = $reply_uid = '';
                    if ($_temp != false) {
                        foreach ($_temp as $_tk1 => $_tv1) {
                            $reply_id .= ',' . $_tv1['id'];
                            $reply_uid .= ',' . $_tv1['comment_user_id'];
                        }
                        foreach ($_temp as $_tk => &$_tv) {
                            $_tv['comment_created_at'] = $this->formDate($_tv['comment_created_at']);
                            // 评论顶
                            $zi_main = $this->comment_zan($_tv['id'], $user_id);
                            $_tv['support'] = $zi_main['support'];
                            $_tv['support_user_flg'] = $zi_main['support_user_flg'];
                            // 评论踩
                            $zi_main = $this->comment_cai($_tv['id'], $user_id);
                            $_tv['dislike'] = $zi_main['dislike'];
                            $_tv['dislike_user_flg'] = $zi_main['dislike_user_flg'];
                            // 消息数据
                            $_tv['main_id'] = $value['id'];
                            $_tv['main_comment_user_id'] = $value['comment_user_id'];
                            $_tv['reply_id'] = $value['id'] . $reply_id;
                            $_tv['reply_comment_user_id'] = $value['comment_user_id'] . $reply_uid;
                            // 用户信息TODO(请求接口)
                            $user_info2 = $this->get_user_info($_tv['comment_user_id']);
                            $_tv['username'] = $user_info2['username'];
                            $_tv['userimg'] = $user_info2['userimg'];
                            // 用户信息TODO(请求接口)
                            $user_info = $this->get_user_info($_tv['comment_to_user_id']);
                            $_tv['comment_to_username'] = $user_info['username'];
                            $_tv['comment_to_userimg'] = $user_info['userimg'];
                            
                            $only_root_data[$key]['children'][] = $_tv;
                        }
                    }
                    // 消息数据
                    $only_root_data[$key]['main_id'] = trim($zhu_ping, ",");
                    $only_root_data[$key]['main_comment_user_id'] = trim($zhu_ping_uid, ",");
                    $only_root_data[$key]['reply_id'] = $value['id'] . $reply_id;
                    $only_root_data[$key]['reply_comment_user_id'] = $value['comment_user_id'] . $reply_uid;
                }
            }
            
            $return_data['comment_list'] = $only_root_data;
            // 表单项具体参数
            $fourm_id = $_REQUEST['form_category_id'];
            if (! empty($fourm_id) && is_numeric($fourm_id)) {
                // 根据fourmid的缓存信息获取字段fourm_idtype_id（表单设定值id）
                $fourm_category_all = $this->get_fourm_category_redis();
                if (isset($fourm_category_all[$fourm_id])) {
                    if (! strrpos($fourm_category_all[$fourm_id]['fourm_idtype_id'], ',')) {
                        // 获取表单设定项缓存信息
                        $fourm_item_all = $this->get_fourm_item_redis();
                        // 对应表单设定
                        $fourm_item_id = $fourm_item_all[$fourm_category_all[$fourm_id]['fourm_idtype_id']];
                        if (isset($fourm_item_id)) {
                            if ($fourm_item_id['fourm_item_idtype'] == self::word_types) {
                                // 文字类型的参数
                                $fourm_item_content = json_decode($fourm_item_id['fourm_item_content'], true);
                                $return_data['fourm_parameter']['fourm_word_prompt'] = $fourm_item_content['word_content_prompt']; // 提示文字
                                $return_data['fourm_parameter']['fourm_word_online'] = $fourm_item_content['word_content_online']; // 评论上限
                            } else {
                                $return_data['fourm_parameter']['fourm_word_prompt'] = '我要回应...';
                                $return_data['fourm_parameter']['fourm_word_online'] = 200;
                            }
                        }
                    } else {
                        $return_data['fourm_parameter']['fourm_word_prompt'] = '我要回应...';
                        $return_data['fourm_parameter']['fourm_word_online'] = 200;
                    }
                    $return_data['fourm_parameter']['fourm_pess'] = $fourm_category_all[$fourm_id]['fourm_pess']; // 修改权限
                    $return_data['fourm_parameter']['fourm_reply'] = $fourm_category_all[$fourm_id]['fourm_reply']; // 评论是否可回复
                    $return_data['fourm_parameter']['fourm_anonymous'] = $fourm_category_all[$fourm_id]['fourm_anonymous']; // 是否匿名
                }
            } else {
                // 默认参数
                $return_data['fourm_parameter']['fourm_word_prompt'] = '我要回应...'; // 提示文字
                $return_data['fourm_parameter']['fourm_word_online'] = 200; // 评论上限
                $return_data['fourm_parameter']['fourm_pess'] = 1; // 修改权限
                $return_data['fourm_parameter']['fourm_reply'] = 0; // 评论是否可回复
                $return_data['fourm_parameter']['fourm_anonymous'] = 0; // 是否匿名
            }
            // 评论总数
            $blog_count = $this->redis->get($url . '_count') ? $this->redis->get($url . '_count') : 0;
            $return_data['count'] = $blog_count;
            if ($only_root_data) {
                echo json_encode($return_data);
                exit();
            } else {
                echo '';
                exit();
            }
        } catch (\Exception $e) {
            echo '数据异常';
            exit();
        }
    }

    public function tags()
    {
        try {
            $user_id = $_REQUEST['user_id'];
            $url = $_REQUEST['key'];
            // $url = 'http://demo.dwnews.com/index/index';
            $page = $_REQUEST['p'] ? $_REQUEST['p'] : 0;
            $showsum = $_REQUEST['sum'] ? $_REQUEST['sum'] : 10;
            
            if ($user_id == 'dw_999999' || empty($user_id)) {
                $sql = 'select main.*,sp.comment_content,sp.comment_attachment from dw_comment main left join dw_comment_exp sp on main.id=sp.id where comment_status in(1,6) and comment_url = ? and comment_parent_id = ? order by id desc limit ' . $page . ' , ' . $showsum;
            } else {
                $sql = 'select main.*,sp.comment_content,sp.comment_attachment from dw_comment main left join dw_comment_exp sp on main.id=sp.id where (comment_status in(1,6) or (comment_status in(4,5) and comment_user_id =' . $user_id . '))  and comment_url = ? and comment_parent_id = ? order by id desc limit ' . $page . ' , ' . $showsum;
            }
            $result = $this->db->query($sql, array(
                $url,
                0
            ));
            $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $data = $result->fetchAll();
            $account_id = '';
            // 主评论数据
            if (isset($data)) {
                $zhu_ping_id = $zhu_ping_uid = '';
                foreach ($data as $key1 => $value1) {
                    $zhu_ping .= ',' . $value1['id'];
                    $zhu_ping_uid .= ',' . $value1['comment_user_id'];
                    $account_id = $value1['comment_channel_area'];
                }
                foreach ($data as $key => $value) {
                    $only_root_data[$key] = $value;
                    $only_root_data[$key]['comment_created_at'] = $this->formDate($value['comment_created_at']);
                    // 评论顶
                    $zan_main = $this->comment_zan($value['id'], $user_id);
                    $only_root_data[$key]['support'] = $zan_main['support'];
                    $only_root_data[$key]['support_user_flg'] = $zan_main['support_user_flg'];
                    // 评论踩
                    $zan_main = $this->comment_cai($value['id'], $user_id);
                    $only_root_data[$key]['dislike'] = $zan_main['dislike'];
                    $only_root_data[$key]['dislike_user_flg'] = $zan_main['dislike_user_flg'];
                    if (! empty($value['comment_user_id'])) {
                        // 个人评分数据
                        $score = CommentScore::find([
                            'comment_url = :comment_url: and comment_id = :comment_id:',
                            'bind' => [
                                'comment_url' => $url,
                                'comment_id' => $value['id']
                            ]
                        ])->toArray();
                        // 标签项
                        if (! empty($score)) {
                            $item_redis = $this->redis->get('fourm_item'); // 表单设定缓存
                            $item_redis = json_decode($item_redis, true);
                            if (isset($item_redis[$score[0]['item_id']])) {
                                // 标签赋值
                                foreach ($score as $score_k => &$score_v) {
                                    $item_redis_byid = $item_redis[$score_v['item_id']]; // 评分中表单设置标签的缓存
                                    $score_val = [];
                                    // 标签扩展项
                                    foreach ($item_redis_byid['fourmCategoryItemExt'] as $i_id_k => $i_id_v) {
                                        $score_val[$i_id_v['id']] = $i_id_v['item_tag_name'];
                                    }
                                    $score_v['item_id_val'] = $item_redis_byid['fourm_item_title'];
                                    $score_v['item_ext_id_val'] = $score_val[$score_v['item_ext_id']];
                                    $score_v['item_ext_id_tag_type'] = $item_redis_byid['fourm_item_tag_type'];
                                    unset($score_v['id']);
                                    unset($score_v['uid']);
                                    unset($score_v['comment_url']);
                                    unset($score_v['form_id']);
                                    unset($score_v['comment_id']);
                                    unset($score_v['comment_time']);
                                    unset($score_v['comment_ip']);
                                }
                            }
                        }
                        $only_root_data[$key]['score'] = $score;
                    } else {
                        $only_root_data[$key]['score'] = [];
                    }
                    $_temp = $this->getSubData($value['id']);
                    $reply_id = $reply_uid = '';
                    if ($_temp != false) {
                        foreach ($_temp as $_tk1 => $_tv1) {
                            $reply_id .= ',' . $_tv1['id'];
                            $reply_uid .= ',' . $_tv1['comment_user_id'];
                        }
                        foreach ($_temp as $_tk => &$_tv) {
                            $_tv['comment_created_at'] = $this->formDate($_tv['comment_created_at']);
                            // 评论顶
                            $zi_main = $this->comment_zan($value['id'], $user_id);
                            $_tv['support'] = $zi_main['support'];
                            $_tv['support_user_flg'] = $zi_main['support_user_flg'];
                            
                            // 评论踩
                            $zi_main = $this->comment_cai($value['id'], $user_id);
                            $_tv['dislike'] = $zi_main['dislike'];
                            $_tv['dislike_user_flg'] = $zi_main['dislike_user_flg'];
                            
                            // 消息数据
                            $_tv['main_id'] = $value['id'];
                            $_tv['main_comment_user_id'] = $value['comment_user_id'];
                            $_tv['reply_id'] = $value['id'] . $reply_id;
                            $_tv['reply_comment_user_id'] = $value['comment_user_id'] . $reply_uid;
                            // 用户信息TODO(请求接口)
                            $user_info = $this->get_user_info($_tv['comment_user_id']);
                            $_tv['username'] = $user_info['username'];
                            $_tv['userimg'] = $user_info['userimg'];
                            // 用户信息TODO(请求接口)
                            $user_info = $this->get_user_info($_tv['comment_to_user_id']);
                            $_tv['comment_to_username'] = $user_info['username'];
                            $_tv['comment_to_userimg'] = $user_info['userimg'];
                            $only_root_data[$key]['children'][] = $_tv;
                        }
                    }
                    // 消息数据
                    $only_root_data[$key]['main_id'] = trim($zhu_ping, ",");
                    $only_root_data[$key]['main_comment_user_id'] = trim($zhu_ping_uid, ",");
                    $only_root_data[$key]['reply_id'] = $value['id'] . $reply_id;
                    $only_root_data[$key]['reply_comment_user_id'] = $value['comment_user_id'] . $reply_uid;
                    
                    // 用户信息TODO(请求接口)
                    $user_info = $this->get_user_info($value['comment_user_id']);
                    $only_root_data[$key]['username'] = $user_info['username'];
                    $only_root_data[$key]['userimg'] = $user_info['userimg'];
                    // 用户信息TODO(请求接口)
                    $user_info = $this->get_user_info($value['comment_to_user_id']);
                    $only_root_data[$key]['comment_to_username'] = $user_info['username'];
                    $only_root_data[$key]['comment_to_userimg'] = $user_info['userimg'];
                }
            }
            if ($only_root_data) {
                $return['flg'] = true;
                $return['data'] = $only_root_data;
                // 举报分类缓存
                $report_type = $this->get_report_category_redis($account_id);
                $return['report_type'] = $report_type;
                echo json_encode($return);
                exit();
            } else {
                $return['flg'] = true;
                $return['data'] = '';
                
                $return['report_type'] = '';
                
                echo json_encode($return);
                exit();
            }
        } catch (\Exception $e) {
            echo '接口异常';
            exit();
        }
    }

    /**
     * **
     * 举报类型缓存
     *
     * @param ing $pid
     *            账户群组id
     */
    private function get_report_category_redis($pid)
    {
        $redis_name = md5('report_type_' . $pid);
        $redis_list = $this->redis->get($redis_name);
        $redis_list = json_decode($redis_list, true);
        $result = array();
        if (! empty($redis_list)) {
            foreach ($redis_list as $k => $v) {
                if ($v['report_type_state'] == 2) {
                    unset($redis_list[$k]);
                } else {
                    $result[] = $v;
                }
            }
            return $result;
        } else {
            $sql = 'select id,report_type_title,report_account_id,report_account_pid,report_type_state,report_type_create from dw_repost_type where report_account_id = ' . $pid . ' or report_account_pid=' . $pid;
            $result = $this->db->query($sql);
            $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $redis = $result->fetchAll();
            if (! empty($redis)) {
                foreach ($redis as $s_key => $s_v) {
                    $_temp[$s_v['id']] = $s_v;
                }
                $value = @json_encode($_temp);
                $this->redis->set($redis_name, $value);
                foreach ($_temp as $k => $v) {
                    if ($v['report_type_state'] == 2) {
                        unset($_temp[$k]);
                    }
                }
                return $_temp;
            } else {
                $value = '';
                $this->redis->set($redis_name, '');
                return array();
            }
        }
    }

    /**
     * 表单类型缓存
     *
     * @param unknown $comment_user_id            
     * @return unknown[]
     *
     */
    private function get_fourm_category_redis()
    {
        $fourm_category = $this->redis->get('fourm_category');
        $fourm_category = json_decode($fourm_category, true);
        if (! empty($fourm_category)) {
            return $fourm_category;
        } else {
            $sql = 'select id,fourm_title,fourm_idtype_id,fourm_order,fourm_meth,fourm_pess,fourm_number,fourm_reply,fourm_anonymous,fourm_account from dw_fourm_category';
            $result = $this->db->query($sql);
            $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $redis = $result->fetchAll();
            if (! empty($redis)) {
                foreach ($redis as $s_key => $s_v) {
                    $_temp[$s_v['id']]['id'] = $s_v['id'];
                    $_temp[$s_v['id']]['fourm_title'] = $s_v['fourm_title'];
                    $_temp[$s_v['id']]['fourm_idtype_id'] = $s_v['fourm_idtype_id'];
                    $_temp[$s_v['id']]['fourm_order'] = $s_v['fourm_order'];
                    $_temp[$s_v['id']]['fourm_meth'] = $s_v['fourm_meth'];
                    $_temp[$s_v['id']]['fourm_pess'] = $s_v['fourm_pess'];
                    $_temp[$s_v['id']]['fourm_number'] = $s_v['fourm_number'];
                    $_temp[$s_v['id']]['fourm_reply'] = $s_v['fourm_reply'];
                    $_temp[$s_v['id']]['fourm_anonymous'] = $s_v['fourm_anonymous'];
                    $_temp[$s_v['id']]['fourm_account'] = $s_v['fourm_account'];
                }
                $value = json_encode($_temp);
                $redis = $this->redis->set('fourm_category', $value);
                return $_temp;
            } else {
                return array();
            }
        }
    }

    /**
     * 表单设定项缓存
     *
     * @param unknown $comment_user_id            
     * @return unknown[]
     *
     */
    private function get_fourm_item_redis()
    {
        $redis = $this->redis->get('fourm_item');
        $redis = json_decode($redis, true);
        if (! empty($redis)) {
            return $redis;
        } else {
            $sql = 'SELECT * FROM dw_fourm_category_item';
            $result = $this->db->query($sql);
            $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $redis = $result->fetchAll();
            if (! empty($redis)) {
                foreach ($redis as $s_key => $s_v) {
                    $a2[$s_v['id']]['id'] = $s_v['id'];
                    $a2[$s_v['id']]['fourm_item_title'] = $s_v['fourm_item_title'];
                    $a2[$s_v['id']]['fourm_item_tag_type'] = $s_v['fourm_item_tag_type'];
                    $a2[$s_v['id']]['fourm_item_idtype'] = $s_v['fourm_item_idtype'];
                    $a2[$s_v['id']]['fourm_item_content'] = $s_v['fourm_item_content'];
                    $a2[$s_v['id']]['fourm_item_is_ver'] = $s_v['fourm_item_is_ver'];
                    $sql2 = 'SELECT * FROM dw_fourm_category_item_ext where item_id = ' . $s_v['id'] . ' order by item_tag_sort asc';
                    $result = $this->db->query($sql2);
                    $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
                    $this->db->getSQLStatement();
                    $redis2 = $result->fetchAll();
                    if (is_array($redis2)) {
                        $a2[$s_v['id']]['fourmCategoryItemExt'] = $redis2;
                    } else {
                        $a2[$s_v['id']]['fourmCategoryItemExt'] = array();
                    }
                }
                $value = @json_encode($a2);
                $this->redis->set('fourm_item', $value);
                return $a2;
            } else {
                $value = '';
                $this->redis->set('fourm_item', '');
                return $value;
            }
        }
    }
    
    // 评论数顶
    private function comment_zan($comment_id, $user_id = null)
    {
        $redis_c = $this->redis->get(md5('comment_support_' . $comment_id));
        $_return['support'] = 0;
        $_return['support_user_flg'] = false;
        if (! empty($redis_c)) {
            $redis_c = json_decode($redis_c, true);
            $_return['support'] = $redis_c['count'] ? $redis_c['count'] : 0;
            if (! empty($user_id) && isset($redis_c['uid'][$user_id])) {
                $_return['support_user_flg'] = true;
            } else {
                $_return['support_user_flg'] = false;
            }
        }
        return $_return;
    }
    
    // 评论数踩
    private function comment_cai($comment_id, $user_id = null)
    {
        $redis_c = $this->redis->get(md5('comment_dislike_' . $comment_id));
        $_return['dislike'] = 0;
        $_return['dislike_user_flg'] = false;
        
        if (! empty($redis_c)) {
            $redis_c = json_decode($redis_c, true);
            $_return['dislike'] = empty($redis_c['count']) ? 0 : $redis_c['count'];
            
            if (! empty($user_id) && isset($redis_c['uid'][$user_id])) {
                $_return['dislike_user_flg'] = true;
            } else {
                $_return['dislike_user_flg'] = false;
            }
        }
        return $_return;
    }

    private function getSubData($pid)
    {
        // $data = Comment::find(
        // [
        // 'comment_parent_id = :comment_parent_id:',
        // 'bind' => [
        // 'comment_parent_id' => $pid
        // ]
        // ])->toArray();
        $sql = 'select main.*,sp.comment_content,sp.comment_attachment from dw_comment main left join dw_comment_exp sp on main.id=sp.id where comment_parent_id = ?';
        $result = $this->db->query($sql, array(
            $pid
        ));
        $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
        $this->db->getSQLStatement();
        $data = $result->fetchAll();
        if (isset($data)) {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * 构建数型结构数据
     *
     * @param unknown $list            
     * @param string $pk            
     * @param string $pid            
     * @param string $child            
     * @param number $root            
     * @return json
     */
    private static function build($list, $pk = 'id', $pid = 'comment_parent_id', $child = 'children', $root = 0)
    {
        $tree = array();
        if (is_array($list)) {
            // 创建基于主键的数组引用
            $refer = array();
            foreach ($list as $key => $data) {
                $refer[$data[$pk]] = & $list[$key];
            }
            foreach ($list as $key => $data) {
                // 判断是否存在parent
                $parentId = $data[$pid];
                if ($root == $parentId) {
                    $tree[] = & $list[$key];
                } else {
                    if (isset($refer[$parentId])) {
                        $parent = & $refer[$parentId];
                        $parent[$child][] = & $list[$key];
                    }
                }
            }
        }
        return $tree;
    }

    /**
     * 评论测试数据
     */
    public function comment_tag($page = null, $showsum = null)
    {}

    private function comment_score($url, $uid)
    {
        // $sum = CommentScore::sum(
        // [
        // 'column'=>'comment_score',
        // 'conditions'=>'comment_url = :comment_url:',
        //
        // 'bind' => [
        // 'comment_url' => $url
        // ]
        // ]);
        // $count_uid =CommentScore::count(
        // [
        // 'conditions'=>'comment_url = :comment_url:',
        // 'group'=>'uid',
        // 'bind' => [
        // 'comment_url' => $url
        // ]
        // ]);
    }

    private function formDate($time)
    {
        $now = time();
        $year = ($now - $time) / (60 * 60 * 24 * 30 * 12);
        if ($year > 1) {
            if ($year > 2) {return date("Y-m-d", $time);}
            return intval($year) . " 年前";
        }
        $Month = ($now - $time) / (60 * 60 * 24 * 30);
        if ($Month > 1) {return intval($Month) . " 月前";}
        $Day = ($now - $time) / (60 * 60 * 24);
        if ($Day > 1) {return intval($Day) . " 天前";}
        $Hours = ($now - $time) / (60 * 60);
        if ($Hours > 1) {return intval($Hours) . " 小时前";}
        $Hours = ($now - $time) / 60;
        if ($Hours > 1) {return intval($Hours) . " 分钟前";}
        $Seconds = $now - $time;
        if ($Seconds > 1) {
            return intval($Seconds) . " 秒前";
        } else {
            return '刚刚';
        }
    }

    public function queue()
    {
        // 连接消息列队
        $put_data = array(
            'commentType' => 'reply',
            'data' => $template,
            'createT' => time()
        );
        $put_data = array(
            'commentType' => 'publish',
            'data' => $template,
            'createT' => time()
        );
        
        $jobId = $this->queue->put(array(
            'user_id' => 4871,
            'form_user_id' => 887,
            'data' => '用户1回复了用户2'
        ));
        while (($job = $this->queue->peekReady()) !== false) {
            $message = $job->getBody();
            var_dump($message);
            $job->delete();
        }
        echo '<pre>';
        var_dump($jobId);
        exit();
    }
}
