<?php
namespace DwComment\Components\Factory;

use DwComment\Library;
use DwComment\Models\Blacklist;
use DwComment\Models\Comment;
use DwComment\Models\CommentExtension;
use DwComment\Models\CommentLog;
use DwComment\Models\CommentScore;
use DwComment\Models\Notify;
use DwComment\Models\Report;
use DwComment\Models\Support;
use DwComment\Models\NotifyCategory;
use DwComment\Models\Tread;
use DwComment\Modules\V1\Controllers\RestController;
use Phalcon\Config;
use Phalcon\Http\Request;

/**
 * 业务处理接口
 * @2016-12-03
 *
 * @author Frank
 */
class CommentOperate extends RestController implements \Psr\Http\Message\CommentInterface
{

    private $content_master_key_data;

    public function __construct()
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::__toString()
     */
    public function __toString()
    {
        // TODO Auto-generated method stub
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::Exception()
     */
    public function Exception($dev, $internalCode, $more)
    {
        return array(
            'dev' => $dev,
            'applicationCode' => $internalCode,
            'more' => $more
        );
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::insert_comment_info()
     * @param $comment_user_id 评论用户uid            
     * @param $comment_to_user_id 评论的回复对象uid            
     * @param $comment_title 评论的文章标题            
     * @param $comment_url 评论的url            
     * @param $comment_parent_id 评论回复的父类id            
     * @param $form_category_id 评论表单id            
     * @param $comment_device 评论来源            
     * @param $comment_content 评论内容            
     * @param array $fourm_item_tag
     *            评分的标签组合 array['data'=>'139*54*1','139*62*1','140*60*1']
     * @param $comment_attachment 图片附件地址            
     * @param $main_comment_user_id 评论首页评论数据作者的uid            
     */
    public function insert_comment_info()
    {
        // 参数获取
        $comment_user_id = isset($_POST['user_id']) ? trim($this->request->getPost('user_id')) : false;
        $comment_to_user_id = isset($_POST['to_user_id']) ? trim($this->request->getPost('to_user_id')) : false;
        $comment_user_nickname = isset($_POST['nickname']) ? trim($this->request->getPost('nickname')) : false;
        $comment_title = isset($_POST['comment_title']) ? $this->request->getPost('comment_title') : '';
        $comment_url = isset($_POST['comment_url']) ? $this->request->getPost('comment_url') : false;
        $comment_parent_id = isset($_POST['parent_id']) ? $this->request->getPost('parent_id') : 0;
        $form_category_id = isset($_POST['form_category_id']) ? $this->request->getPost('form_category_id') : false;
        $comment_device = isset($_POST['device']) ? $this->request->getPost('device') : 'website';
        $comment_content = isset($_POST['content']) ? $this->request->getPost('content') : false;
        $fourm_item_tag = isset($_POST['fourm_item_tag']) ? $this->request->getPost('fourm_item_tag') : false;
        $comment_attachment = isset($_POST['comment_attachment']) ? $this->request->getPost('comment_attachment') : null;
        $main_comment_user_id = isset($_POST['main_comment_user_id']) ? $this->request->getPost('main_comment_user_id') : null;
        if (empty($comment_user_id) || ! isset($comment_to_user_id) || empty($comment_title) || empty($comment_url) || ! is_numeric($comment_parent_id) || empty($form_category_id) || ! is_numeric($form_category_id) || empty($comment_content)) {return $this->Exception(self::model_comment_add, self::error_code1, self::error_code1_msg);}
        $redis = $this->redis; // TONGHUI
        $refresh = $this->redis->get(md5($comment_user_id));
        // 防止灌数据
        $comment_irrigation_flg = $this->check_comment_irrigation_data($comment_user_id);
        if (! $comment_irrigation_flg) {
            return $this->Exception(self::model_comment_add, self::error_code4, self::error_code4_msg);
        } else {
            try {
                // 黑名单检测
                $blacklist_flg = $this->check_blacklist($comment_user_id);
                if ($blacklist_flg == true) {return $this->Exception(self::model_comment_add, self::error_code9, self::error_code9_msg);}
                // 检查表单设置项
                $redis_fourm_category = $this->get_fourm_category_redis();
                if (empty($redis_fourm_category) || ! isset($redis_fourm_category[$form_category_id])) {return $this->Exception(self::model_comment_add, self::error_code5, self::error_code5_msg);}
                // 检查是否重复评论
                $form = $redis_fourm_category[$form_category_id];
                if ($form['fourm_number'] == RestController::FOURM_NUMBER_STATS && $comment_parent_id == RestController::COMMENT_PARENT_FLG) {
                    $flg = Comment::find([
                        'comment_user_id = :comment_user_id: and comment_url = :comment_url:',
                        'bind' => [
                            'comment_user_id' => $comment_user_id,
                            'comment_url' => $comment_url
                        ]
                    ])->toArray();
                    if (! empty($flg)) {return $this->Exception(self::model_comment_add, self::error_code6, self::error_code6_msg);}
                }
                // 是否可以匿名
                if ($form['fourm_anonymous'] == RestController::FOURM_ANONYMOUS_STATS) {
                    if (! $comment_user_id || empty($comment_user_id) || $comment_user_id == RestController::ANONYMOUS) {return $this->Exception(self::model_comment_add, self::error_code7, self::error_code7_msg);}
                }
                // 是否可以回复
                if ($form['fourm_reply'] == RestController::FOURM_REPLY_STATS) {
                    if ($comment_parent_id != RestController::COMMENT_PARENT_FLG) {return $this->Exception(self::model_comment_add, self::error_code8, self::error_code8_msg);}
                }
                // 检查敏感词
                $sensitive = $this->sensitive_check($comment_content, $form['fourm_account']);
                if ($sensitive == 'P0015') {
                    return $this->Exception(self::model_comment_update, self::error_code15, self::error_code15_msg);
                } elseif ($sensitive == 'P0017') {return $this->Exception(self::model_comment_update, self::error_code17, self::error_code17_msg);}
                $sensitive_check = ''; // 检测到敏感词分类
                if (! empty($sensitive)) {
                    $sensitive_check = $this->check_sensitive_type($form['fourm_account'], $sensitive, $comment_content);
                }
                if (isset($sensitive_check['forbid'])) {
                    $word = implode(',', $sensitive_check['forbid']);
                    // 禁止类的敏感词
                    return $this->Exception(self::model_comment_add, self::error_code18, self::error_code18_msg);
                }
                // 评论内容有关敏感词处理后的内容
                if (isset($sensitive_check['content'])) {
                    $comment_content = $sensitive_check['content'];
                }
                // 安全过滤
                $comment_content = \DwComment\Library\Filter::remove_xss($comment_content);
                $ip = \DwComment\Library\ServerNeedle::server_ip();
                $model = new Comment();
                $model->comment_user_id = $comment_user_id;
                $model->comment_user_nickname = $comment_user_nickname;
                $model->comment_to_user_id = $comment_to_user_id;
                $model->comment_title = $comment_title;
                $model->comment_url = $comment_url;
                $model->comment_parent_id = $comment_parent_id;
                $model->comment_channel_area = $form['fourm_account'];
                $model->comment_user_type = $form['id'];
                $model->comment_created_at = time();
                $model->comment_updated_at = time();
                $model->comment_examine_at = time();
                $comment_status = RestController::COMMENT_PUBLIC;
                // 成功返回状态$success_code
                $success_code = self::success_code;
                $success_code_msg = self::success_code_msg;
                if ($form['fourm_meth'] == RestController::COMMENT_METH_STATS) {
                    $comment_status = RestController::COMMENT_AUDITING_TRIAL; // 人工送审
                    $success_code = self::error_code22;
                    $success_code_msg = self::error_code22_msg;
                } elseif (isset($sensitive_check['review'])) {
                    $comment_status = RestController::COMMENT_SENSITIVE; // 敏感词送审
                    $word = implode(',', $sensitive_check['review']);
                    $success_code = self::error_code23;
                    $success_code_msg = self::error_code23_msg;
                }
                $model->comment_status = $comment_status;
                $model->comment_device = $comment_device;
                $model->comment_ip = $ip;
                $this->db->begin();
                $model->save();
                $insertId = $model->getWriteConnection()->lastInsertId($model->getSource());
                // 评论扩展表保存
                $comment_extension = new CommentExtension();
                $comment_extension->id = $insertId;
                $comment_extension->comment_content = $comment_content;
                $comment_extension->comment_dateline = time();
                if ($comment_attachment['pic']) {
                    $comment_attachment = trim($comment_attachment['pic'], ',');
                }
                $comment_extension->comment_attachment = $comment_attachment;
                $comment_extension->save();
                
                // 标签评分表相关
                if ($fourm_item_tag['data']) {
                    $fourm_item_tag = $fourm_item_tag['data']; // array['data'=>'139*54*1','139*62*1','140*60*1']
                    $fourm_item_tag_arr = explode(',', $fourm_item_tag);
                    // 评分具体项缓存
                    $fourm_item_redis = $this->get_fourm_item_redis();
                    foreach ($fourm_item_tag_arr as $tag_k => $tag_v) {
                        $CommentScore = new CommentScore();
                        $CommentScore->uid = $comment_user_id;
                        $CommentScore->comment_url = $comment_url;
                        $CommentScore->form_id = $form_category_id;
                        $item_tag = explode('*', $tag_v);
                        $item_id = trim($item_tag[0]);
                        $item_ext_id = trim($item_tag[1]);
                        $comment_score = trim($item_tag[2]);
                        if (isset($fourm_item_redis[$item_id])) {
                            $CommentScore->item_ext_tag_type = $fourm_item_redis[$item_id]['fourm_item_tag_type'];
                        } else {
                            $this->db->rollback();
                            return $this->Exception(self::model_comment_add, self::error_code20, self::error_code20_msg);
                        }
                        $CommentScore->item_id = $item_id;
                        $CommentScore->item_ext_id = $item_ext_id;
                        $CommentScore->comment_score = $comment_score;
                        $CommentScore->comment_id = $insertId;
                        $CommentScore->comment_time = time();
                        $CommentScore->comment_ip = $ip;
                        $CommentScore->save();
                    }
                }
                // 灌数据设置
                $this->set_comment_irrigation_data($comment_user_id);
                
                // 消息队列
                if ($main_comment_user_id != null) {
                    $notify = [
                        'comment_user_id' => $comment_user_id,
                        'comment_to_user_id' => $comment_to_user_id,
                        'comment_url' => $comment_url,
                        'comment_parent_id' => $comment_parent_id,
                        'comment_title' => $comment_title,
                        'main_comment_user_id' => $main_comment_user_id
                    ];
                    $this->notify_comment_info($notify);
                }
                
                // 评论数缓存
                $this->update_comment_count_redis($comment_url, '+', 1);
                // 评论信息缓存
                $this->update_comment_redis($insertId);
                $_redis = new \DwComment\Library\RedisQueue();
                // Redis parent_list
                if ($comment_parent_id == 0) {
                    $_redis->setContentMasterKey($comment_url, $insertId);
                } else {
                    $_redis->setContentSonKey($comment_parent_id, $insertId);
                }
                $_data = [
                    'id' => $model->id,
                    'comment_user_id' => $model->comment_user_id,
                    'comment_to_user_id' => $model->comment_to_user_id,
                    'comment_user_nickname' => $model->comment_user_nickname,
                    'comment_title' => $model->comment_title,
                    'comment_url' => $model->comment_url,
                    'comment_parent_id' => $model->comment_parent_id,
                    'comment_up' => $model->comment_up,
                    'comment_down' => $model->comment_down,
                    'comment_channel_area' => $model->comment_channel_area,
                    'comment_user_type' => $model->comment_user_type,
                    'comment_created_at' => $model->comment_created_at,
                    'comment_updated_at' => $model->comment_updated_at,
                    'comment_examine_at' => $model->comment_examine_at,
                    'comment_status' => $model->comment_status,
                    'comment_device' => $model->comment_device,
                    'comment_is_lock' => $model->comment_is_lock,
                    'comment_is_hide' => $model->comment_is_hide,
                    'comment_is_report' => $model->comment_is_report,
                    'comment_ip' => $model->comment_ip
                ];
                // 组装数据
                $_redis->setContentDetail($insertId, $_data);
                // Redis son_list
                
                $this->db->commit();
                
                // $redis->set(md5('comment_user_id'), $comment_user_id);
                // $redis->expire(md5('comment_user_id'), 1);
                
                return $this->Exception(self::model_comment_add, $success_code, $success_code_msg);
            } catch (\Exception $e) {
                $this->db->rollback();
                return $this->Exception(self::model_comment_add, self::error_code2, self::error_code2_msg);
            }
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::get_article_comment_info()
     */
    public function get_article_comment_info()
    {   
        $CommentUrl = isset($_POST['key']) ? $this->request->getPost('key') : null;
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : null;
        $page = isset($_POST['p']) ? $this->request->getPost('p') : 0;
        $showsum = isset($_POST['sum']) ? $this->request->getPost('sum') : 10;
        $fourm_id = isset($_POST['form_category_id']) ? $this->request->getPost('form_category_id') : null;
        $form_item_type = isset($_POST['form_item_type']) ? $this->request->getPost('form_item_type') : RestController::FOURM_ITEM_COMMENT_WORD;
        if (empty($CommentUrl) || empty($fourm_id) || empty($user_id) || ! is_numeric($fourm_id)) {return $this->Exception(self::model_comment_list, self::error_code1, self::error_code1_msg);}
        try {
            // 评论表单缓存信息
            $fourm_category_all = $this->get_fourm_category_redis();
            // 获取表单设定项缓存信息
            $fourm_item_redis_all = $this->get_fourm_item_redis();
            
            $_redis = new \DwComment\Library\RedisQueue();
            
            // 评论Comment Model
            $Comment = '\DwComment\Models\Comment as Comment';
            // 评论CommentExtension Model
            $CommentExtension = '\DwComment\Models\CommentExtension as CommentExtension';
            
            // 登录用户，可以查看自己的评论被审核的数据
            $public_report_where = self::COMMENT_PUBLIC .','. self::COMMENT_REPORT;//发布和举报送审的数据
            $sensitive_auditing_where = self::COMMENT_AUDITING_TRIAL .','. self::COMMENT_SENSITIVE;//人工审核和敏感词审核的数据
            if( $user_id == self::ANONYMOUS || empty($user_id)){
            	$commet_stats_where = ' Comment.comment_status in('.$public_report_where.') ';
            }else{
            	$commet_stats_where = ' (Comment.comment_status in('.$public_report_where.') or (Comment.comment_status in('.$sensitive_auditing_where. ') and Comment.comment_user_id ="'.$user_id.'"))';
            }
            // 如果Redis 没有评论取最新50条主评论
            if (! $_redis->checkContentMasterKey($CommentUrl)) {
                $article_comment_redis_num = RestController::ARTICLE_COMMENT_REDIS_NUM;
                $_phql = 'SELECT Comment.id FROM ' . $Comment . ' where Comment.comment_url = :comment_url: and Comment.comment_parent_id = :comment_parent_id:  order by Comment.id DESC limit ' . $article_comment_redis_num;
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
                $master_k = $this->content_master_key_data = $_redis->getContentMasterKey($CommentUrl);
            }
            
            // 最新评论50条的id
            $master_k = implode(',', $master_k);
            
            if ($page < 1) {
                // 首页从缓存中获取
                $parentID = ' and Comment.id in (' . $master_k . ')';
            } else {
                // 其他页从数据库中获取
                $parentID = ' and Comment.comment_parent_id = 0 ';
            }
            // 数据库查询
           
            $phql = 'SELECT Comment.id,Comment.comment_user_id,Comment.comment_to_user_id,Comment.comment_user_nickname,Comment.comment_title,Comment.comment_url,Comment.comment_parent_id,Comment.comment_up,Comment.comment_down,Comment.comment_channel_area,Comment.comment_user_type,Comment.comment_created_at,Comment.comment_updated_at,Comment.comment_examine_at,Comment.comment_status,Comment.comment_device,Comment.comment_is_lock,Comment.comment_is_hide,Comment.comment_is_report,Comment.comment_ip,CommentExtension.comment_content,CommentExtension.comment_attachment FROM ' . $Comment . ' left join ' . $CommentExtension . ' on Comment.id=CommentExtension.id 
    				where '.$commet_stats_where.' 
    				and Comment.comment_url = :comment_url: ' . $parentID . ' order by Comment.id DESC limit ' . $page . ' , ' . $showsum;
            $data = $this->modelsManager->executeQuery($phql, [
                'comment_url' => $CommentUrl
            ])->toArray();
            // 主评论数据
            $only_root_data = [];
            $account_id = '';
            if (isset($data)) {
                // 获取首页评论的主屏用户uid和评论id（发送消息使用）
                $zhu_ping_id = $zhu_ping_uid = '';
                foreach ($data as $key1 => $value1) {
                    $zhu_ping .= ',' . $value1['id'];
                    $zhu_ping_uid .= ',' . $value1['comment_user_id'];
                    $account_id = $value1['comment_channel_area'];
                }
                // 主屏数据处理
                $FunctionCommon = new \DwComment\Library\FunctionCommon();
                foreach ($data as $key => &$value) {
                    $only_root_data[$key] = $value;
                    if ($form_item_type == RestController::FOURM_ITEM_COMMENT_SCORE && ! empty($value['comment_user_id'])) {
                        // 个人评分数据
                        $score = CommentScore::find([
                            'comment_url = :comment_url: and comment_id = :comment_id:',
                            'bind' => [
                                'comment_url' => $CommentUrl,
                                'comment_id' => $value['id']
                            ]
                        ])->toArray();
                        // 标签项
                        if (! empty($score)) {
                            // 标签赋值
                            foreach ($score as $score_k => &$score_v) {
                                $item_id = $score_v['item_id']; // 表单设定项id
                                $item_ext_id = $score_v['item_ext_id']; // 表单设定项-标签扩展id
                                $item_redis_byid = $fourm_item_redis_all[$item_id]; // 评分中表单设置标签的缓存
                                if (isset($item_redis_byid)) {
                                    $score_val = [];
                                    // 格式化标签扩展项数据
                                    foreach ($item_redis_byid['fourmCategoryItemExt'] as $i_id_k => $i_id_v) {
                                        $score_val[$item_ext_id]['item_tag_name'] = $i_id_v['item_tag_name'];
                                        $score_val[$item_ext_id]['item_tag_type'] = $i_id_v['item_tag_type'];
                                    }
                                    
                                    // 标签项名称
                                    $score_v['item_id_val'] = $item_redis_byid['fourm_item_title'];
                                    // 标签项中-扩展项名称
                                    $score_v['item_ext_id_val'] = $score_val[$item_ext_id]['item_tag_name'];
                                    $score_v['item_ext_id_tag_type'] = $score_val[$item_ext_id]['item_tag_type'];
                                    
                                    // 去除不需要的数据
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
                    $only_root_data[$key]['comment_created_at'] = $FunctionCommon::formate($value['comment_created_at']);
                    // 评论顶
                    $zan_main = $this->comment_support_redis_byuser($value['id'], $user_id);
                    $only_root_data[$key]['support'] = $zan_main['support'];
                    $only_root_data[$key]['support_user_flg'] = $zan_main['support_user_flg'];
                    // 评论踩
                    $zan_main = $this->comment_dislike_redis_byuser($value['id'], $user_id);
                    $only_root_data[$key]['dislike'] = $zan_main['dislike'];
                    $only_root_data[$key]['dislike_user_flg'] = $zan_main['dislike_user_flg'];
                    // 用户信息TODO(请求接口)
                    $user_info = $this->get_comment_list_user_info($value['comment_user_id']);
                    $only_root_data[$key]['username'] = $value['comment_user_nickname'];
                    $only_root_data[$key]['userimg'] = $user_info['userimg'];
                    // 用户信息TODO(请求接口)
                    $user_info = $this->get_comment_list_user_info($value['comment_to_user_id']);
                    $only_root_data[$key]['comment_to_username'] = $value['comment_user_nickname'];
                    $only_root_data[$key]['comment_to_userimg'] = $user_info['userimg'];
                    $_temp = $this->getSubData($value['id']);
                    $reply_id = $reply_uid = '';
                    if ($_temp != false) {
                        // 回复评论的评论作者uid
                        foreach ($_temp as $_tk1 => $_tv1) {
                            $reply_id .= ',' . $_tv1['id'];
                            $reply_uid .= ',' . $_tv1['comment_user_id'];
                        }
                        // 回复数据处理
                        foreach ($_temp as $_tk => &$_tv) {
                            $_tv['comment_created_at'] = $FunctionCommon::formate($_tv['comment_created_at']);
                            // 评论顶
                            $zi_main = $this->comment_support_redis_byuser($_tv['id'], $user_id);
                            $_tv['support'] = $zi_main['support'];
                            $_tv['support_user_flg'] = $zi_main['support_user_flg'];
                            // 评论踩
                            $zi_main = $this->comment_dislike_redis_byuser($_tv['id'], $user_id);
                            $_tv['dislike'] = $zi_main['dislike'];
                            $_tv['dislike_user_flg'] = $zi_main['dislike_user_flg'];
                            // 消息数据
                            $_tv['main_id'] = $value['id'];
                            $_tv['main_comment_user_id'] = $value['comment_user_id'];
                            $_tv['reply_id'] = $value['id'] . $reply_id;
                            $_tv['reply_comment_user_id'] = $value['comment_user_id'] . $reply_uid;
                            // 用户信息TODO(请求接口)
                            $user_info2 = $this->get_comment_list_user_info($_tv['comment_user_id']);
                            $_tv['username'] = $user_info2['username'];
                            $_tv['userimg'] = $user_info2['userimg'];
                            // 用户信息TODO(请求接口)
                            $user_info = $this->get_comment_list_user_info($_tv['comment_to_user_id']);
                            $_tv['comment_to_username'] = $user_info['username'];
                            $_tv['comment_to_userimg'] = $user_info['userimg'];
                            
                            $only_root_data[$key]['children'][] = $_tv;
                        }
                    }
                    // 主屏消息数据
                    $only_root_data[$key]['main_id'] = trim($zhu_ping, ",");
                    $only_root_data[$key]['main_comment_user_id'] = trim($zhu_ping_uid, ",");
                    $only_root_data[$key]['reply_id'] = $value['id'] . $reply_id;
                    $only_root_data[$key]['reply_comment_user_id'] = $value['comment_user_id'] . $reply_uid;
                }
            }
            
            $fourm_parameter = [];
            
            // 根据评论表单中的，表单项设置字段，来获取具体的表单项设定信息
            if (isset($fourm_category_all[$fourm_id])) {
                // 评论表单中，表单项设项的id
                $item_id = $fourm_category_all[$fourm_id]['fourm_idtype_id'];
                $item_id_array = explode(',', $item_id);
                
                foreach ($item_id_array as $item_k => $item_v) {
                    // 具体的表单设定项中的某设定项的缓存数据
                    $item_id_redis_byid = $fourm_item_redis_all[$item_v];
                    if (isset($item_id_redis_byid) && $item_id_redis_byid['fourm_item_idtype'] == RestController::FOURM_ITEM_WORD) {
                        // 文字类型的表单
                        $fourm_item_content = json_decode($item_id_redis_byid['fourm_item_content'], true);
                        $fourm_parameter['fourm_word_prompt'] = $fourm_item_content['word_content_prompt']; // 提示文字
                        $fourm_parameter['fourm_word_online'] = $fourm_item_content['word_content_online']; // 评论上限
                        break;
                    }
                }
                if (empty($fourm_parameter)) {
                    // 默认参数
                    $fourm_parameter['fourm_word_prompt'] = '我要回应...';
                    $fourm_parameter['fourm_word_online'] = 200;
                }
                // 评论表单有关权限
                $fourm_parameter['fourm_pess'] = $fourm_category_all[$fourm_id]['fourm_pess']; // 修改权限
                $fourm_parameter['fourm_reply'] = $fourm_category_all[$fourm_id]['fourm_reply']; // 评论是否可回复
                $fourm_parameter['fourm_anonymous'] = $fourm_category_all[$fourm_id]['fourm_anonymous']; // 是否匿名
            } else {
                return $this->Exception(self::model_comment_list, self::error_code5, self::error_code5_msg);
            }
            
            // 评论总数
            $blog_count_redis = $this->redis->get($CommentUrl . '_count');
            $blog_count = $blog_count_redis ? $blog_count_redis : 0;
            $return_data['count'] = $blog_count;
            $return_data['comment_list'] = $only_root_data;
            $return_data['fourm_parameter'] = $fourm_parameter;
            // 举报分类缓存
            $report_type = $this->get_report_category_redis($account_id);
            $return_data['report_type'] = $report_type;
            return $return_data;
        } catch (\Exception $e) {
            return $this->Exception(self::model_comment_list, self::error_code2, self::error_code2_msg);
        }
    }

    /**
     *
     * {@综合评分}
     *
     * @see \Psr\Http\Message\CommentInterface::get_article_composite_score()
     * @param $comment_url 地址            
     */
    public function get_article_composite_score()
    {
        $comment_url = isset($_POST['comment_url']) ? $this->request->getPost('comment_url') : false;
        
        if (! empty($comment_url)) {
            $comment_url = explode(',', $comment_url);
            try {
                $comment_score = [];
                foreach ($comment_url as $key => $v) {
                    $sql = "select item_id,AVG(comment_score) as score from dw_comment_score where comment_url = ? group by item_id ";
                    $result = $this->db->query($sql, [
                        $v
                    ]);
                    $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
                    $this->db->getSQLStatement();
                    $data = $result->fetchAll();
                    
                    if (! empty($data)) {
                        $item_redis = $this->get_fourm_item_redis();
                        foreach ($data as $score_k => &$score_v) {
                            $item_redis_byid = $item_redis[$score_v['item_id']]; // 标签缓存
                            $score_v['item_id_val'] = $item_redis_byid['fourm_item_title']; // 标签名称
                            $score_v['item_id_type'] = $item_redis_byid['fourm_item_tag_type']; // 标签打分类型
                            $score_v['score'] = number_format($score_v['score'], 1);
                        }
                        $comment_score[$v] = $data;
                    } else {
                        $comment_score[$v] = '';
                    }
                    $count = $this->redis->get($v . '_count');
                    
                    if (! empty($count)) {
                        $comment_score[$v . '_count'] = $count;
                    } else {
                        $comment_score[$v . '_count'] = 0;
                    }
                }
                
                return $comment_score;
            } catch (\Exception $e) {
                return $this->Exception(self::model_comment_score, self::error_code2, self::error_code2_msg);
            }
        } else {
            return $this->Exception(self::model_comment_score, self::error_code1, self::error_code1_msg);
        }
    }

    /**
     * 个人评论列表
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::get_personal_comment_info()
     * @param $comment_user_id 评论用户uid            
     * @param $page 页码            
     * @param $showsum 每页条数            
     *
     */
    public function get_personal_comment_info()
    {
        $comment_user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : null;
        $page = isset($_POST['p']) ? (int) $this->request->getPost('p') : 0;
        $showsum = isset($_POST['sum']) ? (int) $this->request->getPost('sum') : 50;
        
        if (empty($comment_user_id)) {
            return $this->Exception(self::model_comment_list, self::error_code1, self::error_code1_msg);
        } else {
            $sql = 'select main.id,main.comment_title,main.comment_parent_id,sp.comment_content,sp.comment_attachment from dw_comment main left join dw_comment_exp sp on main.id=sp.id where  comment_user_id = ? order by id desc limit ' . $page . ' , ' . $showsum;
            $result = $this->db->query($sql, array(
                $comment_user_id
            ));
            $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $data = $result->fetchAll();
            $only_root_data = array();
            if (isset($data)) {
                try {
                    foreach ($data as $key => $value) {
                        // 自己数据处理
                        $only_root_data[$key] = $value;
                        // 评论表单
                        $fourm_category = $this->get_fourm_category_redis();
                        if (isset($fourm_category[$value['comment_user_type']])) {
                            $only_root_data[$key]['comment_user_type_name'] = $fourm_category[$value['comment_user_type']]['fourm_title'];
                        } else {
                            $only_root_data[$key]['comment_user_type_name'] = '未知来源';
                        }
                    }
                    // 当前用户评论总数
                    $Comment_count = Comment::count([
                        'comment_user_id = :comment_user_id:',
                        'bind' => [
                            'comment_user_id' => $comment_user_id
                        ]
                    ]);
                    $Comment_info = [
                        'data' => $only_root_data,
                        'count' => $Comment_count
                    ];
                    return $Comment_info;
                } catch (Exception $e) {
                    return $this->Exception(self::model_comment_list, self::error_code2, self::error_code2_msg);
                }
            } else {
                return $this->Exception(self::model_comment_list, self::error_code3, self::error_code3_msg);
            }
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::search_comment_info()
     */
    public function search_comment_info()
    {
        // TODO Auto-generated method stub
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::edit_comment_info()
     * @param $id 编辑的评论id            
     * @param $comment_user_id 评论用户uid            
     * @param $comment_url 评论的url            
     * @param $form_category_id 评论表单id            
     * @param $comment_device 评论来源            
     * @param $comment_content 评论内容            
     * @param array $fourm_item_tag
     *            评分的标签组合 array['data'=>'139*54*1','139*62*1','140*60*1']
     * @param $comment_attachment 图片附件地址            
     */
    public function edit_comment_info()
    {
        $id = isset($_POST['id']) ? $this->request->getPost('id') : null;
        $comment_user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : false;
        $comment_url = isset($_POST['comment_url']) ? $this->request->getPost('comment_url') : false;
        $comment_device = isset($_POST['device']) ? $this->request->getPost('device') : 'website';
        $comment_content = isset($_POST['content']) ? $this->request->getPost('content') : null;
        $form_category_id = isset($_POST['form_category_id']) ? $this->request->getPost('form_category_id') : false;
        $fourm_item_tag = isset($_POST['fourm_item_tag']) ? $this->request->getPost('fourm_item_tag') : false;
        $comment_attachment = isset($_POST['comment_attachment']) ? $this->request->getPost('comment_attachment') : null;
        
        if (! is_numeric($id) || ! is_numeric($form_category_id) || empty($comment_user_id) || empty($comment_url) || empty($comment_content) || empty($comment_device) || empty($comment_device)) {return $this->Exception(self::model_comment_update, self::error_code1, self::error_code1_msg);}
        // 开启事务
        try {
            // 检测数据是否存在
            $del_comment = $this->get_comment_redis($id);
            if (empty($del_comment)) {return $this->Exception(self::model_comment_update, self::error_code3, self::error_code3_msg);}
            // 非本人编辑
            if ($del_comment['comment_user_id'] != $comment_user_id) {return $this->Exception(self::model_comment_update, self::error_code10, self::error_code10_msg);}
            // 编辑表单与原数据表单不符
            if ($del_comment['comment_user_type'] != $form_category_id) {return $this->Exception(self::model_comment_update, self::error_code19, self::error_code19_msg);}
            // 非发布状态无法编辑
            if ($del_comment['comment_status'] != RestController::COMMENT_PUBLIC_STATS) {return $this->Exception(self::model_comment_update, self::error_code16, self::error_code16_msg);}
            // 黑名单
            $blacklist_flg = $this->check_blacklist($comment_user_id);
            if ($blacklist_flg == true) {return $this->Exception(self::model_comment_update, self::error_code9, self::error_code9_msg);}
            // 检查评论表单设置
            $redis_fourm_category = $this->get_fourm_category_redis();
            // var_dump($redis_fourm_category);
            if (empty($redis_fourm_category) || ! isset($redis_fourm_category[$form_category_id])) {return $this->Exception(self::model_comment_update, self::error_code5, self::error_code5_msg);}
            // 检查是否可以编辑
            $form = $redis_fourm_category[$form_category_id];
            if ($form['fourm_pess'] == RestController::FOURM_PESS_STATS) {return $this->Exception(self::model_comment_update, self::error_code11, self::error_code11_msg);}
            // 事务开始
            $this->db->begin();
            // 检查敏感词
            $sensitive = $this->sensitive_check($comment_content, $form['fourm_account']);
            if ($sensitive == 'P0015') {
                return $this->Exception(self::model_comment_update, self::error_code15, self::error_code15_msg);
            } elseif ($sensitive == 'P0017') {return $this->Exception(self::model_comment_update, self::error_code17, self::error_code17_msg);}
            $sensitive_check = ''; // 检测到敏感词分类
            if (! empty($sensitive)) {
                $sensitive_check = $this->check_sensitive_type($form['fourm_account'], $sensitive, $comment_content);
            }
            if (isset($sensitive_check['forbid'])) {
                $word = implode(',', $sensitive_check['forbid']);
                // 禁止类的敏感词
                return $this->Exception(self::model_comment_update, self::error_code18, self::error_code18_msg);
            }
            
            if (isset($sensitive_check['review'])) {
                $comment_status = RestController::COMMENT_SENSITIVE; // 敏感词送审
                $Comment = Comment::FindFirst($id);
                $Comment->comment_status = $comment_status;
                $Comment->update();
                $this->update_comment_redis($id);
            }
            
            if (isset($sensitive_check['content'])) {
                $comment_content = $sensitive_check['content'];
            }
            // 安全过滤
            $comment_content = \DwComment\Library\Filter::remove_xss($comment_content);
            
            // 扩展表更新
            $comment_extension = CommentExtension::FindFirst($id);
            $comment_extension->comment_content = $comment_content;
            if ($comment_attachment['pic']) {
                $comment_attachment = trim($comment_attachment['pic'], ',');
            }
            $comment_extension->comment_attachment = $comment_attachment;
            $comment_extension->update();
            // 标签评分表相关
            if ($fourm_item_tag['data']) {
                $fourm_item_tag = $fourm_item_tag['data'];
                $fourm_item_tag_arr = explode(',', $fourm_item_tag);
                // $fourm_item_tag = ['139*54','139*62','140*60'];
                $fourm_item_redis = $this->get_fourm_item_redis();
                $stat = CommentScore::find([
                    'conditions' => 'comment_id = {comment_id} AND uid = {user_id}',
                    'bind' => [
                        'user_id' => $comment_user_id,
                        'comment_id' => $id
                    ]
                ])->delete();
                $ip = \DwComment\Library\ServerNeedle::server_ip();
                foreach ($fourm_item_tag_arr as $tag_k => $tag_v) {
                    $CommentScore = new CommentScore();
                    $CommentScore->uid = $comment_user_id;
                    $CommentScore->comment_url = $comment_url;
                    $CommentScore->form_id = $form_category_id;
                    $item_tag = explode('*', $tag_v);
                    $item_id = trim($item_tag[0]);
                    $item_ext_id = trim($item_tag[1]);
                    $comment_score = trim($item_tag[2]);
                    if (isset($fourm_item_redis[$item_id])) {
                        $CommentScore->item_ext_tag_type = $fourm_item_redis[$item_id]['fourm_item_tag_type'];
                    } else {
                        $this->db->rollback();
                        return $this->Exception(self::model_comment_update, self::error_code20, self::error_code20_msg);
                    }
                    $CommentScore->item_id = $item_id;
                    $CommentScore->item_ext_id = $item_ext_id;
                    $CommentScore->comment_score = $comment_score;
                    $CommentScore->comment_id = $id;
                    $CommentScore->comment_time = time();
                    $CommentScore->comment_ip = $ip;
                    $CommentScore->save();
                }
            }
            // 提交事务
            $this->db->commit();
            return $this->Exception(self::model_comment_update, self::success_code, self::success_code_msg);
        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->Exception(self::model_comment_update, self::error_code2, self::error_code2_msg);
        }
    }

    /**
     * {评论点赞（顶）}
     *
     * @see \Psr\Http\Message\CommentInterface::support_comment_info()
     * @param int $id
     *            评论id
     * @param $user_id 点赞用户uid            
     * @param $form_user_id 被点赞用户uid            
     *
     */
    public function support_comment_info()
    {
        $id = isset($_POST['id']) ? $this->request->getPost('id') : null;
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : null; // 点赞用户uid
        $form_user_id = isset($_POST['form_user_id']) ? $this->request->getPost('form_user_id') : null;
        
        if (! is_numeric($id) || empty($user_id) || empty($form_user_id)) {
            return $this->Exception(self::model_comment_support, self::error_code1, self::error_code1_msg);
        } else {
            // 开启事务
            
            try {
                // 检测黑名单
                $blacklist_flg = $this->check_blacklist($user_id);
                
                if ($blacklist_flg == true) {return $this->Exception(self::model_comment_support, self::error_code9, self::error_code9_msg);}
                // 判断数据是否存在
                $report_data = $this->get_comment_redis($id);
                if (! empty($report_data)) {
                    // 评论赞缓存
                    $redis = $this->get_comment_support_redis($id);
                    if (! empty($redis) && isset($redis['uid'][$user_id]) && $user_id != RestController::ANONYMOUS) {return $this->Exception(self::model_comment_support, self::error_code12, self::error_code12_msg);}
                    // 评论点赞表更新
                    $this->db->begin();
                    $support = new Support();
                    $support->comment_id = $id;
                    $support->user_id = $user_id;
                    $support->form_user_id = $form_user_id;
                    $support->dateline = time();
                    $support->ip = \DwComment\Library\ServerNeedle::server_ip();
                    $support->save();
                    if ($this->db->commit()) {
                        if ($this->update_comment_support_redis($id)) {return $this->Exception(self::model_comment_support, self::success_code, self::success_code_msg);}
                    }
                } else {
                    return $this->Exception(self::model_comment_support, self::error_code3, self::error_code3_msg);
                }
            } catch (\Exception $e) {
                $this->db->rollback();
                return $this->Exception(self::model_comment_support, self::error_code2, self::error_code2_msg);
            }
        }
    }

    /**
     *
     * {@删除评论}
     *
     * @see \Psr\Http\Message\CommentInterface::delete_comment_info()
     * @param int $id
     *            删除的评论id
     * @param $comment_user_id 操作用户uid            
     */
    public function delete_comment_info()
    {
        $id = isset($_POST['id']) ? (int) $this->request->getPost('id') : null;
        $comment_user_id = isset($_POST['comment_user_id']) ? $this->request->getPost('comment_user_id') : null;
        if (! is_numeric($id) || empty($comment_user_id)) {return $this->Exception(self::model_comment_del, self::error_code1, self::error_code1_msg);}
        try {
            $this->db->begin();
            // 检测数据是否存在
            $del_comment = $this->get_comment_redis($id);
            
            if (empty($del_comment)) {return $this->Exception(self::model_comment_del, self::error_code3, self::error_code3_msg);}
            if ($del_comment['comment_user_id'] != $comment_user_id) {return $this->Exception(self::model_comment_del, self::error_code10, self::error_code10_msg);}
            $comment = Comment::findFirst($id);
            $comment_extension = CommentExtension::findFirst($id);
            $comment->delete();
            $comment_extension->delete();
            
            // 评论记录
            $stat = CommentLog::find([
                'conditions' => 'comment_id = {id}',
                'bind' => [
                    'id' => $id
                ]
            ])->delete();
            // 评分删除
            $stat = CommentScore::find([
                'conditions' => 'comment_id = {comment_id}',
                'bind' => [
                    'comment_id' => $id
                ]
            ])->delete();
            
            $this->del_relevant_redis($id);
            $del_count = 1;
            // 数据下的回复
            $ziji = Comment::find([
                'comment_parent_id = :id:',
                'bind' => [
                    'id' => $id
                ]
            ])->toArray();
            
            if (! empty($ziji)) {
                // 主节点删除
                $stat = Comment::find([
                    'conditions' => 'comment_parent_id = {id}',
                    'bind' => [
                        'id' => $id
                    ]
                ])->delete();
                
                foreach ($ziji as $key => $v) {
                    $comment_extension = CommentExtension::findFirst($v['id']);
                    $comment_extension->delete();
                    $this->del_relevant_redis($v['id']);
                    $del_count ++;
                }
            }
            
            // 评论数缓存
            $this->update_comment_count_redis($del_comment[0]['comment_url'], '-', $del_count);
            $this->db->commit();
            return $this->Exception(self::model_comment_del, self::success_code, self::success_code_msg);
        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->Exception(self::model_comment_del, self::error_code2, self::error_code2_msg);
        }
    }

    /**
     *
     * {@取消点赞}
     *
     * @see \Psr\Http\Message\CommentInterface::unsupport_commnet_info()
     * @param int $id
     *            评论id
     * @param $user_id 取消点赞用户uid            
     */
    public function unsupport_comment_info()
    {
        $id = isset($_POST['id']) ? $this->request->getPost('id') : null;
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : null;
        if (! is_numeric($id) || empty($user_id)) {
            return $this->Exception(self::model_comment_support, self::error_code1, self::error_code1_msg);
        } else {
            // 开启事务
            try {
                // 检测黑名单
                $blacklist_flg = $this->check_blacklist($user_id);
                if ($blacklist_flg == true) {return $this->Exception(self::model_comment_support, self::error_code9, self::error_code9_msg);}
                // 判断数据是否存在
                $report_data = $this->get_comment_redis($id);
                if (! empty($report_data)) {
                    // 评论赞缓存
                    $redis = $this->get_comment_support_redis($id);
                    if (! empty($redis) && isset($redis['uid'][$user_id])) {
                        $this->db->begin();
                        $stat = Support::find([
                            'conditions' => 'user_id = {user_id} AND comment_id = {comment_id}',
                            'bind' => [
                                'user_id' => $user_id,
                                'comment_id' => $id
                            ]
                        ])->delete();
                        if ($this->db->commit()) {
                            $this->update_comment_support_redis($id);
                            return $this->Exception(self::model_comment_support, self::success_code, self::success_code_msg);
                        }
                    } else {
                        return $this->Exception(self::model_comment_support, self::error_code13, self::error_code13_msg);
                    }
                } else {
                    return $this->Exception(self::model_comment_support, self::error_code3, self::error_code3_msg);
                }
            } catch (\Exception $e) {
                $this->db->rollback();
                return $this->Exception(self::model_comment_support, self::error_code2, self::error_code2_msg);
            }
        }
    }

    /**
     *
     * {@评论踩}
     *
     * @see \Psr\Http\Message\CommentInterface::dislike_commnet_info()
     * @param int $id
     *            评论id
     * @param $user_id 点踩用户            
     * @param $form_user_id 被点踩评论的作者uid            
     */
    public function dislike_comment_info()
    {
        $id = isset($_POST['id']) ? $this->request->getPost('id') : null;
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : null; // 点赞用户uid
        $form_user_id = isset($_POST['form_user_id']) ? $this->request->getPost('form_user_id') : null;
        
        if (! is_numeric($id) || empty($user_id) || empty($form_user_id)) {
            return $this->Exception(self::model_comment_dislike, self::error_code1, self::error_code1_msg);
        } else {
            // 开启事务
            try {
                // 检测黑名单
                $blacklist_flg = $this->check_blacklist($user_id);
                if ($blacklist_flg == true) {return $this->Exception(self::model_comment_dislike, self::error_code9, self::error_code9_msg);}
                // 判断数据是否存在
                $report_data = $this->get_comment_redis($id);
                if (! empty($report_data)) {
                    // 评论赞缓存
                    $redis = $this->get_comment_dislike_redis($id);
                    if (! empty($redis) && isset($redis['uid'][$user_id]) && $user_id != RestController::ANONYMOUS) {return $this->Exception(self::model_comment_dislike, self::error_code24, self::error_code24_msg);}
                    // 评论扩展表评论表更新
                    $this->db->begin();
                    $support = new Tread();
                    $support->comment_id = $id;
                    $support->user_id = $user_id;
                    $support->form_user_id = $form_user_id;
                    $support->dateline = time();
                    $support->ip = \DwComment\Library\FunctionCommon::server_ip();
                    $support->save();
		    $this->db->commit();
                    // 更新缓存
                    $this->update_comment_dislike_redis($id);
                    return $this->Exception(self::model_comment_dislike, self::success_code, self::success_code_msg);
                } else {
                    return $this->Exception(self::model_comment_dislike, self::error_code3, self::error_code3_msg);
                }
            } catch (\Exception $e) {
                $this->db->rollback();
                return $this->Exception(self::model_comment_dislike, self::error_code2, self::error_code2_msg);
            }
        }
    }

    /**
     *
     * {@取消点踩}
     *
     * @see \Psr\Http\Message\CommentInterface::undislike_commnet_info()
     * @param int $id
     *            评论id
     * @param $user_id 点踩用户            
     */
    public function undislike_comment_info()
    {
        $id = isset($_POST['id']) ? $this->request->getPost('id') : null;
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : null;
        if (! is_numeric($id) || empty($user_id)) {
            return $this->Exception(self::model_comment_dislike, self::error_code1, self::error_code1_msg);
        } else {
            // 开启事务
            try {
                // 检测黑名单
                $blacklist_flg = $this->check_blacklist($user_id);
                if ($blacklist_flg == true) {return $this->Exception(self::model_comment_dislike, self::error_code9, self::error_code9_msg);}
                // 判断数据是否存在
                $report_data = $this->get_comment_redis($id);
                if (! empty($report_data)) {
                    // 评论赞缓存
                    $redis = $this->get_comment_dislike_redis($id);
                    if (! empty($redis) && isset($redis['uid'][$user_id])) {
                        $stat = Tread::find([
                            'conditions' => 'user_id = {user_id} AND comment_id = {comment_id}',
                            'bind' => [
                                'user_id' => $user_id,
                                'comment_id' => $id
                            ]
                        ])->delete();
                        $this->update_comment_dislike_redis($id);
                        return $this->Exception(self::model_comment_dislike, self::success_code, self::success_code_msg);
                    } else {
                        return $this->Exception(self::model_comment_dislike, self::error_code25, self::error_code25_msg);
                    }
                } else {
                    return $this->Exception(self::model_comment_dislike, self::error_code3, self::error_code3_msg);
                }
            } catch (\Exception $e) {
                return $this->Exception(self::model_comment_dislike, self::error_code2, self::error_code2_msg);
            }
        }
    }

    /**
     *
     * {@评论举报}
     *
     * @see \Psr\Http\Message\CommentInterface::report_comment_info()
     * @param int $report_comment_id
     *            举报的评论id
     * @param $report_from_uid 发起举报用户UID            
     * @param int $report_idtype
     *            举报的评论id
     * @param string $report_content
     *            举报的评论id
     */
    public function report_comment_info()
    {
        
        // 评论ID
        $report_comment_id = isset($_POST['comment_id']) ? $this->request->getPost('comment_id') : null;
        // 发起举报UID
        $report_from_uid = isset($_POST['report_from_uid']) ? $this->request->getPost('report_from_uid') : null;
        // 举报分类ID
        $report_idtype = isset($_POST['report_idtype']) ? $this->request->getPost('report_idtype') : null;
        // 举报内容
        $report_content = isset($_POST['report_content']) ? $this->request->getPost('report_content') : null;
        $flg = true;
        if (! is_numeric($report_comment_id) || ! is_numeric($report_idtype) || empty($report_from_uid) || empty($report_content)) {return $this->Exception(self::model_comment_report, self::error_code1, self::error_code1_msg);}
        try {
            // 检测黑名单
            $blacklist_flg = $this->check_blacklist($report_from_uid);
            if ($blacklist_flg == true) {return $this->Exception(self::model_comment_report, self::error_code9, self::error_code9_msg);}
            $report_data = $this->get_comment_redis($report_comment_id);
            // 判断数据是否存在
            if (! empty($report_data)) {
                // 判断是否已经举报过
                $report_redis = $this->get_report_commnet_redis($report_comment_id);
                if (! empty($report_redis) && isset($report_redis['uid'][$report_from_uid])) {return $this->Exception(self::model_comment_report, self::error_code21, self::error_code21_msg);}
                $report = new Report();
                $report->report_idtype = $report_idtype;
                $report->report_uid = $report_data['comment_user_id'];
                $report->report_from_uid = $report_from_uid;
                $report->report_url = $report_data['comment_url'];
                $report->report_content_title = $report_data['comment_title'];
                $report->report_content = $report_content;
                $report->report_create = time();
                $report->report_comment_id = $report_comment_id;
                $report->report_ip = \DwComment\Library\ServerNeedle::server_ip();
                $report->report_account = $report_data['comment_channel_area'];
                $report->report_status = 1;
                $this->db->begin();
                $a = $report->save();
                if (! empty($report_redis)) {
                    $count = $report_redis['count'] + 1; // 新的这次举报次数未在缓存列表中
                    $report_approval = $report_redis['report_approval']; // 送审次数
                } else {
                    $count = 1;
                    $report_approval = 0;
                }
                $send_num = $this->get_paramers_redis($report_data['comment_channel_area']);
                if (! empty($send_num)) {
                    $send_num1 = $send_num['parameter_report_num'];
                    $send_num2 = $send_num['parameter_report_brush'];
                } else {
                    $send_num1 = RestController::SEND_NUM1;
                    $send_num2 = RestController::SEND_NUM2; // 默认送审次数
                }
                if ($count == $send_num1 && $report_approval == 0) {
                    $report_approval = 1;
                    // 评论表更新,第一次送审
                    $comment = Comment::FindFirst($report_comment_id);
                    $comment->comment_status = RestController::COMMENT_REPORT;
                    $comment->update();
                    // 评论信息缓存
                    $this->update_comment_redis($report_comment_id);
                } elseif ($count == $send_num2 && $report_approval == 1 && $report_data['comment_status'] != RestController::COMMENT_REPORT) {
                    // 已经经过一次审核的数据
                    $report_approval = 2;
                    // 评论表更新,第二次送审,
                    $comment = Comment::FindFirst($report_comment_id);
                    $comment->comment_status = RestController::COMMENT_REPORT; // 举报送审状态
                    $comment->update();
                    // 评论信息缓存
                    $this->update_comment_redis($report_comment_id);
                }
                
                // 跟新举报次数缓存
                $this->update_comment_report_redis($report_comment_id, $report_approval);
                $this->db->commit();
                return $this->Exception(self::model_comment_report, self::success_code, self::success_code_msg);
            } else {
                return $this->Exception(self::model_comment_report, self::error_code3, self::error_code3_msg);
            }
        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->Exception(self::model_comment_report, self::error_code2, self::error_code2_msg);
        }
    }

    /**
     *
     * {@获取举报类型}
     *
     * @see \Psr\Http\Message\CommentInterface::get_report_category()
     * @param int $account_id
     *            账户群组id
     */
    public function get_report_category()
    {
        
        // 举报评论所属账户
        $report_account = isset($_POST['account_id']) ? $this->request->getPost('account_id') : null;
        if (! is_numeric($report_account)) {return $this->Exception(self::model_comment_report, self::error_code1, self::error_code1_msg);}
        //$_pid = new \DwComment\Library\FunctionCommon();
        // 账户cache
        //$pid = $_pid->get_account_pid($_POST['account_id']);
        $pid = $this->get_account_pid($_POST['account_id']);
        if ($pid == false) {return $this->Exception(self::model_comment_report, self::error_code15, self::error_code15_msg);}
        // 获取缓存
        $redis = $this->get_report_category_redis($pid);
        if (! empty($redis)) {
            return $redis;
        } else {
            return $this->Exception(self::model_comment_report, self::error_code3, self::error_code3_msg);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::get_emoticon_info()
     * @param int $account_id
     *            账户群组id
     */
    public function get_emoticon_info()
    {
        $account_id = isset($_POST['channel_area']) ? $this->request->getPost('channel_area') : null;
        if (! is_numeric($account_id)) {
            return $this->Exception(self::model_comment_emotion, self::error_code1, self::error_code1_msg);
        } else {
            // 开启事务
            try {
                //$_pid = new \DwComment\Library\FunctionCommon();
                // 账户cache
                //$pid = $_pid->get_account_pid($account_id);
                $pid = $this->get_account_pid($account_id);
                if ($pid == false) {return $this->Exception(self::model_comment_emotion, self::error_code15, self::error_code15_msg);}
                // 表情cache
                $emotion = $this->get_emotion_redis($pid);
                // 表情分类cache
                $category = $this->get_emotion_category_redis($pid);
                $i = 0;
                $cateTag = $imgStr = $imgArrbyCate = $new = '';
                // 表情按类分组
                if (! empty($category) && ! empty($emotion)) {
                    foreach ($emotion as $k_e => $v_e) {
                        $img = $this->getEmoticon($v_e);
                        $imgarr = $imgarrList = [];
                        $new[$v_e['emoticon_cate_id']]['title'] = $category[$v_e['emoticon_cate_id']];
                        $imgarr['icon'] = $img[1];
                        $imgarr['value'] = $v_e['emoticon_name'];
                        $imgarrList[] = $imgarr;
                        $new[$v_e['emoticon_cate_id']]['data'][] = $imgarr;
                    }
                    return $new;
                } else {
                    return $this->Exception(self::model_comment_emotion, self::error_code3, self::error_code3_msg);
                }
            } catch (\Exception $e) {
                return $this->Exception(self::model_comment_emotion, self::error_code2, self::error_code2_msg);
            }
        }
    }

    /**
     *
     * {@评论表单中的表单项设定}
     *
     * @see \Psr\Http\Message\CommentInterface::get_fourm_category_item()
     * @param int $form_category_id
     *            评论表单id
     * @return array
     */
    public function get_fourm_category_item()
    {
        $form_id = isset($_POST['form_category_id']) ? $this->request->getPost('form_category_id') : false;
        if (! empty($form_id)) {
            $fourm_category = $this->get_fourm_category_redis();
            if (isset($fourm_category[$form_id])) {
                $fourm_item = $this->get_fourm_item_redis();
                
                $fourm_item_id = $fourm_category[$form_id]['fourm_idtype_id'];
                $fourm_item_array = explode(',', $fourm_item_id);
                $ret_data = [];
                foreach ($fourm_item_array as $key => $v) {
                    if (isset($fourm_item[$v])) {
                        // if ($fourm_item[$v]['fourm_item_idtype'] == 3) {
                        $ret_data[] = $fourm_item[$v];
                        // }
                    }
                }
                return $ret_data;
            } else {
                return $this->Exception(self::model_fourm_item_tag, self::error_code3, self::error_code3_msg);
            }
        } else {
            return $this->Exception(self::model_fourm_item_tag, self::error_code1, self::error_code1_msg);
        }
    }


    /**
     *
     * {@获取用户临时信息}
     *
     * @see \Psr\Http\Message\CommentInterface::get_user_info()
     * @param int $user_id
     *            用户uid、
     */
    public function temp_user_info()
    {
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : false;
        if (empty($user_id)) {return $this->Exception(self::model_user, self::error_code1, self::error_code1_msg);}
        $sql = 'select id,email,username from dw_temp_user where id = ' . $user_id;
        $user_info = $this->db->query($sql);
        $user_info->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
        $this->db->getSQLStatement();
        $data = $user_info->fetchAll();
        if (! empty($data)) { // $data = json_encode($data[0]);
return $data[0];}
        return $this->Exception(self::model_user, self::error_code3, self::error_code3_msg);
    }

    /**
     *
     * {@获取用户临时信息}
     *
     * @see \Psr\Http\Message\CommentInterface::get_user_info()
     * @param int $user_id
     *            用户uid、
     */
    private function get_comment_list_user_info($temp_user_id = null)
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

    /**
     *
     * {@添加黑名单}
     *
     * @see \Psr\Http\Message\CommentInterface::insert_user_blacklist()
     * @param $user_id 要添加的黑名单用户uid            
     * @param $account_id 操作者的账户群组            
     * @param $action_uid 管理员操作者uid            
     * @param $blacklist_level 黑名单等级            
     */
    public function insert_user_blacklist()
    {
        $user_id = isset($_POST['user_id']) ? trim($this->request->getPost('user_id')) : false;
        $account_id = isset($_POST['account_id']) ? trim($this->request->getPost('account_id')) : false;
        $action_uid = isset($_POST['action_uid']) ? trim($this->request->getPost('action_uid')) : false;
        // 黑名单等级 默认正常黑名单
        $blacklist_level = isset($_POST['blacklist_level']) ? trim($this->request->getPost('blacklist_level')) : RestController::BLACK_LEVEL1;
        
        // 获取父id
        //$FunctionCommon = new \DwComment\Library\FunctionCommon();
        //$account_list = $FunctionCommon->get_auth_account_redis();
        $account_list = $this->get_auth_account_redis();
        if (! isset($account_list[$account_id])) {return $this->Exception(self::model_blacklist, self::error_code15, self::error_code15_msg);}
        if (is_numeric($action_uid) && is_numeric($account_id) && in_array($blacklist_level, [
            1,
            2,
            3
        ])) {
            try {
                $key = md5($user_id);
                $redis_blacklist = $this->get_user_blacklist_redis();
                if (! empty($redis_blacklist) && isset($redis_blacklist[$key])) {
                    return $this->Exception(self::model_blacklist, self::error_code14, self::error_code14_msg);
                } else {
                    // 开启事务
                    $this->db->begin();
                    $account_pid = $account_list[$account_id]['pid'];
                    if ($account_pid == 0) {
                        $account_pid = $account_id;
                    }
                    $model = new Blacklist();
                    $model->blacklist_uid = $user_id;
                    $model->blacklist_action_uid = $action_uid;
                    $model->blacklist_create = time();
                    $model->blacklist_account_id = $account_id;
                    $model->blacklist_account_pid = $account_pid;
                    $model->blacklist_level = $blacklist_level;
                    $insertId = $model->save();
                    
                    // 冻结用户
                    if ($blacklist_level == RestController::BLACK_LEVEL2) {
                        $conditons = 'comment_user_id = :comment_user_id: ';
                        $parameters = [
                            'comment_user_id' => $user_id
                        ];
                        
                        $customer = Comment::find([
                            $conditons,
                            'bind' => $parameters
                        ])->toArray();
                        
                        if ($customer) {
                            $comment_status = RestController::COMMENT_FROZEN; // 用户被冻结
                            foreach ($customer as $v) {
                                $reason = $v['comment_status'];
                                $manager = Comment::findFirst($v['id']);
                                $manager->comment_status = $comment_status;
                                $manager->update();
                                
                                $v['comment_status'] = $comment_status;
                                // 更新缓存
                                $value = json_encode($v);
                                $this->redis->set('comment_stat_' . $v['id'], $value);
                                // 操作日志
                                $CommentLog = new CommentLog();
                                $CommentLog->comment_id = $v['id'];
                                $CommentLog->comment_status = $comment_status;
                                $CommentLog->operation_reason = $reason;
                                $CommentLog->operation_id = $action_uid;
                                $CommentLog->operation_time = time();
                                $CommentLog->save();
                            }
                        }
                    }
                    
                    // 提交事务
                    $this->db->commit();
                    $redis_blacklist[$key]['blacklist_uid'] = $user_id;
                    $redis_blacklist[$key]['blacklist_action_uid'] = $action_uid;
                    $redis_blacklist[$key]['blacklist_account_id'] = $account_id;
                    $redis_blacklist[$key]['blacklist_account_pid'] = $account_pid;
                    $redis_blacklist[$key]['blacklist_level'] = $blacklist_level;
                    $redis_blacklist = json_encode($redis_blacklist);
                    $this->redis->set(RestController::BLACKLIST, $redis_blacklist);
                    return $this->Exception(self::model_blacklist, self::success_code, self::success_code_msg);
                }
            } catch (\Exception $e) {
                return $this->Exception(self::model_blacklist, self::error_code2, self::error_code2_msg);
            }
        } else {
            return $this->Exception(self::model_blacklist, self::error_code1, self::error_code1_msg);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::remove_user_blacklist()
     * @param $user_id 要移除的黑名单用户uid            
     */
    public function remove_user_blacklist()
    {
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : false;
        try {
            // 开启事务
            $this->db->begin();
            if (empty($user_id)) {
                return $this->Exception(self::model_blacklist, self::error_code1, self::error_code1_msg);
            } else {
                $key = md5($user_id);
                $redis_blacklist = $this->get_user_blacklist_redis();
                
                if (empty($redis_blacklist) || ! isset($redis_blacklist[$key])) {
                    return $this->Exception(self::model_blacklist, self::error_code3, self::error_code3_msg);
                } else {
                    // 黑名单删除
                    Blacklist::find([
                        'blacklist_uid = :uid:',
                        'bind' => [
                            'uid' => $user_id
                        ]
                    ])->delete();
                    if ($redis_blacklist[$key]['blacklist_level'] == RestController::BLACK_LEVEL2) {
                        // 冻结用户，调整评论状态
                        $comment_data = Comment::find([
                            'comment_user_id = :uid:',
                            'bind' => [
                                'uid' => $user_id
                            ]
                        ])->toArray();
                        // 评论信息循环更新为原来的状态值
                        if (! empty($comment_data)) {
                            foreach ($comment_data as $v) {
                                $sql = 'select id,comment_id,comment_status,operation_reason,operation_id,operation_time from dw_comment_log where comment_id = ' . $v['id'] . ' order by id desc limit 1';
                                $result = $this->db->query($sql);
                                $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
                                $this->db->getSQLStatement();
                                $comment_log = $result->fetchAll();
                                if (! empty($comment_log)) {
                                    $comment_status = $comment_log[0]['operation_reason'];
                                    // 更新评论到原状态
                                    $sql = 'UPDATE dw_comment SET comment_status = ' . $comment_status . ' WHERE id=' . $v['id'];
                                    $result = $this->db->query($sql);
                                    $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
                                    $this->db->getSQLStatement();
                                    $flg = $result->execute();
                                }
                            }
                        }
                        // 更新黑名单缓存
                        unset($redis_blacklist[$key]);
                        $redis_blacklist = json_encode($redis_blacklist);
                        $this->redis->set(RestController::BLACKLIST, $redis_blacklist);
                        // 提交事务
                        $this->db->commit();
                    }
                    return $this->Exception(self::model_blacklist, self::success_code, self::success_code_msg);
                }
            }
        } catch (\Exception $e) {
            $this->db->rollback();
            return $this->Exception(self::model_blacklist, self::error_code2, self::error_code2_msg);
        }
    }

    /**
     *
     * {@获取消息列表}
     *
     * @see \Psr\Http\Message\CommentInterface::get_queue()
     * @param $user_id 用户uid            
     * @param $p 获取消息开始数            
     * @param $sum 本次获取消息数            
     */
    public function get_queue()
    {
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : null;
        $p = isset($_POST['p']) ? (int) $this->request->getPost('p') : 0;
        $sum = isset($_POST['sum']) ? (int) $this->request->getPost('sum') : 50;
        if (is_numeric($user_id)) {
            try {
                $Notify_data = Notify::find([
                    'to_uid = :to_uid: and read = 0',
                    'bind' => [
                        'to_uid' => $user_id
                    ],
                    'order' => "id DESC",
                    'limit' => [
                        'number' => $sum,
                        'offset' => $p
                    ]
                ])->toArray();
                $Notify_count = Notify::count([
                    'to_uid = :to_uid: and read = 0',
                    'bind' => [
                        'to_uid' => $user_id
                    ]
                ]);
                if (! empty($Notify_data)) {
                    foreach ($Notify_data as $key => &$v) {
                        $v['created_at'] = \DwComment\Library\FunctionCommon::formate($v['created_at']);
                    }
                }
                $notify_info = [
                    'data' => $Notify_data,
                    'count' => $Notify_count
                ];
                return $notify_info;
            } catch (\Exception $e) {
                return $this->Exception(self::model_queue, self::error_code2, self::error_code2_msg);
            }
        } else {
            return $this->Exception(self::model_queue, self::error_code1, self::error_code1_msg);
        }
    }

    /**
     *
     * {@删除消息}
     *
     * @see \Psr\Http\Message\CommentInterface::del_queue()
     * @param $user_id 用户uid            
     * @param $id 删除的消息id            
     */
    public function del_queue()
    {
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : false;
        $id = isset($_POST['id']) ? $this->request->getPost('id') : false;
        if (is_numeric($user_id) && is_numeric($id)) {
            try {
                $flg = Notify::find([
                    'to_uid = :to_uid: and id = :id:',
                    'bind' => [
                        'to_uid' => $user_id,
                        'id' => $id
                    ]
                ])->delete();
                return $this->Exception(self::model_queue, self::success_code, self::success_code_msg);
            } catch (\Exception $e) {
                return $this->Exception(self::model_queue, self::error_code2, self::error_code2_msg);
            }
        } else {
            return $this->Exception(self::model_queue, self::error_code1, self::error_code1_msg);
        }
    }

    /**
     *
     * {@读取消息}
     *
     * @see \Psr\Http\Message\CommentInterface::read_notify()
     * @param $user_id 用户uid            
     * @param $notify_id 读取的消息id            
     */
    public function read_notify()
    {
        $notify_id = isset($_POST['notify_id']) ? $this->request->getPost('notify_id') : false;
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : false;
        if (empty($notify_id) || empty($user_id)) {return $this->Exception(self::model_read_notify, self::error_code1, self::error_code1_msg);}
        // 根据消息id修改为已读状态
        $sql = 'UPDATE dw_notify SET `read` = "1"  WHERE id = "' . $notify_id . '" and to_uid = "' . $user_id . '"';
        $notify = $this->db->query($sql);
        return $this->Exception(self::model_read_notify, self::success_code, self::success_code_msg);
    }

    /**
     *
     * {@用户消息列表暂时无用}
     *
     * @see \Psr\Http\Message\CommentInterface::user_notify_info()
     * @param $user_id 用户uid            
     * @param $p 获取消息开始数            
     * @param $sum 本次获取消息数            
     */
    public function user_notify_info()
    {
        $user_id = isset($_POST['user_id']) ? $this->request->getPost('user_id') : false;
        $p = isset($_POST['p']) ? $this->request->getPost('p') : 1;
        $sum = isset($_POST['sum']) ? $this->request->getPost('sum') : 10;
        if (empty($user_id)) {
            return $this->Exception(self::model_read_notify, self::error_code1, self::error_code1_msg);
        } else {
            $sql = "select id,extra,created_at,link from dw_notify where  from_uid = " . $user_id . " order by created_at desc limit " . $p . "," . $sum;
            $notify_info = $this->db->query($sql);
            $notify_info->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $data = $notify_info->fetchAll();
            if (! empty($data)) {
                $data = json_encode($data);
                echo $data;
            } else {
                return $this->Exception(self::model_read_notify, self::error_code3, self::error_code3_msg);
            }
        }
    }
    
    /**
     *
     * {@获取用户信息-暂时无用}
     *
     * @see \Psr\Http\Message\CommentInterface::get_user_info()
     * @param int $user_id
     *            用户uid、
     */
    public function get_user_info()
    {
    	
    	$user_id = isset($_POST['user_id']) ? (int) $this->request->getPost('user_id') : false;
    	// 开启事务
    	try {
    		$code = '';
    		for ($i = 1; $i <= 4; $i ++) {
    			$code .= chr(rand(97, 122));
    		}
    		$user_info = array(
    				'username' => $code
    		);
    		return $user_info;
    	} catch (\Exception $e) {
    		return $this->Exception(self::model_user, self::error_code2, self::error_code2_msg);
    	}
    }

    /**
     * 格式化数据
     *
     * @param array $array
     *            评论数据id
     * @param string $field
     *            数据字段
     * @author tonghui
     */
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
     * 获取评论回复数据
     *
     * @param int $pid
     *            主屏id
     * @author tonghui
     */
    private function getSubData($pid)
    {
        
        // 评论Comment Model
        $Comment = '\DwComment\Models\Comment as Comment';
        // 评论CommentExtension Model
        $CommentExtension = '\DwComment\Models\CommentExtension as CommentExtension';
        $phql = 'SELECT Comment.id,Comment.comment_user_id,Comment.comment_to_user_id,Comment.comment_user_nickname,Comment.comment_title,Comment.comment_url,Comment.comment_parent_id,Comment.comment_up,Comment.comment_down,Comment.comment_channel_area,Comment.comment_user_type,Comment.comment_created_at,Comment.comment_updated_at,Comment.comment_examine_at,Comment.comment_status,Comment.comment_device,Comment.comment_is_lock,Comment.comment_is_hide,Comment.comment_is_report,Comment.comment_ip,CommentExtension.comment_content,CommentExtension.comment_attachment FROM ' . $Comment . ' left join ' . $CommentExtension . ' on Comment.id=CommentExtension.id where  Comment.comment_parent_id = :comment_parent_id:  order by Comment.id DESC';
        $data = $this->modelsManager->executeQuery($phql, [
            'comment_parent_id' => $pid
        ])->toArray();
        if (isset($data)) {return $data;}
        return false;
    }

    /**
     * 灌数据检测
     *
     * @param $user_id 评论用户uid            
     * @author tonghui
     */
    private function check_comment_irrigation_data($user_id)
    {
        $user_id_commentnum = $this->redis->get(RestController::COMMENT_IRRIGATION . $user_id);
        if (! empty($user_id_commentnum)) {
            $comment_num = $this->config['comment_irrigation']->comment_irrigation_num;
            if ($user_id_commentnum >= $comment_num) {return false;}
            return true;
        }
        {
            return true;
        }
    }

    /**
     * 灌数据设置
     *
     * @param $user_id 评论用户uid            
     * @author tonghui
     */
    private function set_comment_irrigation_data($user_id)
    {
        $user_id_commentnum = $this->redis->get(RestController::COMMENT_IRRIGATION . $user_id);
        if (! empty($user_id_commentnum)) {
            $user_id_commentnum = $user_id_commentnum + 1;
        } else {
            $user_id_commentnum = 1;
        }
        $expire_time = $this->config['comment_irrigation']->comment_irrigation_time;
        $this->redis->setex(RestController::COMMENT_IRRIGATION . $user_id, $expire_time, $user_id_commentnum);
    }

    /**
     * 获取通知消息
     * 入库入队列
     *
     * @author sunwei
     */
    private function notify_comment_info($data)
    {
        $comment_user_id = $data['comment_user_id']; // 添加评论人ID
        $comment_to_user_id = $data['comment_to_user_id']; // 被@人id
        $comment_url = $data['comment_url']; // URL
        $comment_parent_id = $data['comment_parent_id']; // 添加评论的PID
        $comment_title = $data['comment_title']; // 评论标题
        $main_comment_user_id = $data['main_comment_user_id']; // 要发消息的评论UID串
        
        if ($comment_parent_id >= 0) {
            // 所有id
            $id_all = explode(',', $main_comment_user_id);
            // 去重
            $all_uid = array_unique($id_all);
            
            foreach ($all_uid as $key => $v) {
                // 排除自己的id
                if ($v != $comment_user_id) {
                    if ($v != RestController::ANONYMOUS) {
                        // 确认模板
                        if ($comment_parent_id > 0) {
                            // 是否为主评uid
                            if ($v == $all_uid[0]) {
                                $category_id = RestController::RREPLY_TEMPLATE;
                            } else {
                                // 判断是否为被@人uid
                                if ($v == $comment_to_user_id) {
                                    $category_id = RestController::RREPLY_TEMPLATE;
                                } else {
                                    $category_id = RestController::COMMENT_TEMPLATE;
                                }
                            }
                        } else {
                            $category_id = RestController::COMMENT_TEMPLATE;
                        }
                        $category_type = $this->get_notify_cag($category_id);
                        // 判断
                        if ($category_type != null) {
                            // 消息模板
                            $message_id = $category_type['commentTypeId'];
                            $message_type = $category_type['commentType'];
                            $message = $category_type['data'];
                            // 正则替换
                            $message_content = preg_replace("/\{from\.username\}/", $comment_user_id, $message);
                            if ($message_id == 3) {
                                $message_content = preg_replace("/\{article\_title\}/", $comment_title, $message_content);
                            }
                            // 入队列参数
                            $queue_reply_data['commentType'] = $message_type;
                            $queue_reply_data['data'] = $message_content;
                            $queue_reply_data['url'] = $comment_url;
                            $queue_reply_data['createT'] = time();
                            // 入库参数
                            $notify_data['from_uid'] = $comment_user_id;
                            $notify_data['to_uid'] = $v;
                            $notify_data['category_id'] = $message_id;
                            
                            // 消息队列
                            $this->add_notify_info($queue_reply_data, $notify_data);
                        }
                    }
                }
            }
        }
    }

    /*
     * 通知消息入库入队列
     * @param data
     * @author sunwei
     */
    private function add_notify_info($queue_reply_data, $notify_data)
    {
        // 添加消息列队
        $jobId = $this->queue->put($queue_reply_data);
        while (($job = $this->queue->peekReady()) !== false) {
            // 读取消息队列
            $message = $job->getBody();
            // 入库
            $notify_model = new Notify();
            $notify_model->from_uid = $notify_data['from_uid'];
            $notify_model->to_uid = $notify_data['to_uid'];
            $notify_model->category_id = $notify_data['category_id'];
            $notify_model->extra = $message['data'];
            $notify_model->created_at = $message['createT'];
            $notify_model->read = 0;
            $notify_model->link = $message['url'];
            $notify_model->save();
            // 从队列中删除
            $job->delete();
        }
    }

    /*
     * 输出消息分类
     * @author sunwei
     * return string
     */
    private function get_notify_cag($id)
    {
        $notify_category = $this->get_message_template_redis();
        switch ($id) {
            case RestController::RREPLY_TEMPLATE:
                $extra['commentTypeId'] = RestController::RREPLY_TEMPLATE;
                $extra['commentType'] = $notify_category['reply']['name'];
                $extra['data'] = $notify_category['reply']['title'];
                break;
            case RestController::SUGGEST_TEMPLATE:
                $extra['commentTypeId'] = RestController::SUGGEST_TEMPLATE;
                $extra['commentType'] = $notify_category['suggest']['name'];
                $extra['data'] = $notify_category['suggest']['title'];
                break;
            case RestController::COMMENT_TEMPLATE:
                $extra['commentTypeId'] = RestController::COMMENT_TEMPLATE;
                $extra['commentType'] = $notify_category['comment']['name'];
                $extra['data'] = $notify_category['comment']['title'];
                break;
            case RestController::FAVOURITE_TEMPLATE:
                $extra['commentTypeId'] = RestController::FAVOURITE_TEMPLATE;
                $extra['commentType'] = $notify_category['favourite']['name'];
                $extra['data'] = $notify_category['favourite']['title'];
                break;
            case RestController::UP_ARTICLE_TEMPLATE:
                $extra['commentTypeId'] = RestController::UP_ARTICLE_TEMPLATE;
                $extra['commentType'] = $notify_category['up_article']['name'];
                $extra['data'] = $notify_category['up_article']['title'];
                break;
            default:
                return null;
        }
        return $extra;
    }

    /**
     * 获取表情
     *
     * @param $data 表情数据            
     */
    private function getEmoticon($data)
    {
        $img = [];
        if (! empty($data)) {
            $url = RestController::DOMAIN_HOST;
            $img[0] = $url . '/upload/' . $data['emoticon_url'];
            $img[1] = $url . "/upload/" . $data['emoticon_url'];
        }
        return $img;
    }

    /**
     *
     * 获取表情缓存
     *
     * @param ing $account_pid
     *            账户群组父id
     */
    private function get_emotion_redis($account_pid)
    {
        $pid = $account_pid;
        $redis = $this->redis->get(md5('emoticon_' . $pid));
        $redis = json_decode($redis, true);
        if (! empty($redis)) {
            return $redis;
        } else {
            $redis_name = md5('emoticon_' . $pid);
            $sql = 'select id,emoticon_cate_id,emoticon_name,emoticon_url,emoticon_account_id,emoticon_account_pid,emoticon_create_time,emoticon_update_time,emoticon_status from dw_emoticon where emoticon_account_pid=' . $pid . ' or emoticon_account_id = ' . $pid;
            $result = $this->db->query($sql);
            $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $data = $result->fetchAll();
            if (! empty($data)) {
                foreach ($data as $s_key => $s_v) {
                    $a2[$s_v['id']]['id'] = $s_v['id'];
                    $a2[$s_v['id']]['emoticon_cate_id'] = $s_v['emoticon_cate_id'];
                    $a2[$s_v['id']]['emoticon_name'] = $s_v['emoticon_name'];
                    $a2[$s_v['id']]['emoticon_url'] = $s_v['emoticon_url'];
                }
                $value = @json_encode($a2);
                $this->redis->set($redis_name, $value);
                return $a2;
            } else {
                $value = '';
                $this->redis->set($redis_name, $value);
                return $value;
            }
        }
    }

    /**
     *
     * 获取表情分类缓存
     *
     * @param ing $account_pid
     *            账户群组父id
     */
    private function get_emotion_category_redis($account_pid)
    {
        $redis = $this->redis->get(md5('emoticon_category_' . $account_pid));
        $redis = json_decode($redis, true);
        if (! empty($redis)) {
            return $redis;
        } else {
            $redis_name = md5('emoticon_category_' . $account_pid);
            $sql = 'select id,emoticon_category_name,emoticon_account_id,emoticon_account_pid,emoticon_category_create_time,emoticon_category_update_time,emoticon_category_status from dw_emoticon_category where emoticon_account_pid=' . $account_pid . ' or emoticon_account_id = ' . $account_pid;
            $result = $this->db->query($sql);
            $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $data = $result->fetchAll();
            if (! empty($data)) {
                foreach ($data as $s_key => $s_v) {
                    $a2[$s_v['id']] = $s_v['emoticon_category_name'];
                }
                $value = @json_encode($a2);
                $this->redis->set($redis_name, $value);
                return $a2;
            } else {
                $value = '';
                $this->redis->set($redis_name, $value);
                return $value;
            }
        }
    }

    /**
     * 评论举报缓存
     *
     * @param unknown $comment_user_id            
     * @return unknown[]
     */
    private function get_report_commnet_redis($comment_id)
    {
        // 评论举报缓存
        $redis = $this->redis->get(md5('comment_report_' . $comment_id));
        $redis = json_decode($redis, true);
        if (! empty($redis)) {return $redis;}
        return $this->update_comment_report_redis($comment_id);
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
        if (! empty($redis_list)) {
            foreach ($redis_list as $k => $v) {
                if ($v['report_type_state'] == 2) {
                    unset($redis_list[$k]);
                }
            }
            return $redis_list;
        } else {
            $sql = 'select id,report_type_title,report_account_id,report_account_pid,report_type_state,report_type_create from dw_repost_type where report_account_id = ' . $pid . ' or report_account_pid=' . $pid;
            $result = $this->db->query($sql);
            $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $redis = $result->fetchAll();
            if (! empty($redis)) {
                foreach ($redis as $s_key => $s_v) {
                    $a2[$s_v['id']] = $s_v;
                }
                $value = @json_encode($a2);
                $this->redis->set($redis_name, $value);
                foreach ($a2 as $k => $v) {
                    if ($v['report_type_state'] == 2) {
                        unset($a2[$k]);
                    }
                }
                return $a2;
            } else {
                $value = '';
                $this->redis->set($redis_name, '');
                return array();
            }
        }
    }

    /**
     * 更新举报缓存
     *
     * @param int $comment_id
     *            评论id
     * @param int $report_flg
     *            送审次数
     */
    private function update_comment_report_redis($comment_id, $report_flg = 0)
    {
        $Report = Report::find([
            'report_comment_id = :report_comment_id: and report_status = 1',
            'bind' => [
                'report_comment_id' => $comment_id
            ]
        ])->toArray();
        $redis = [];
        if (! empty($Report)) {
            $i = 0;
            foreach ($Report as $key => $v) {
                $redis['uid'][$v['report_from_uid']] = $v['report_from_uid'];
                $i ++;
            }
            $redis['count'] = $i;
            $redis['report_approval'] = $report_flg; // 此数据被送审的次数
        }
        $this->redis->set(md5('comment_report_' . $comment_id), json_encode($redis));
        return $redis;
    }

    /**
     * 删除评论相关缓存
     *
     * @param int $comment_id
     *            评论id
     * @return null
     */
    private function del_relevant_redis($comment_id)
    {
        // 评论状态、赞、举报
        if (is_array($comment_id)) {
            foreach ($comment_id as $key => $v) {
                $this->redis->del('comment_stat_' . $v);
                $this->redis->del(md5('comment_support_' . $v));
                $this->redis->del(md5('comment_report_' . $v));
                $this->redis->del(md5('comment_dislike_' . $v));
            }
        } else {
            $this->redis->del('comment_stat_' . $comment_id);
            $this->redis->del(md5('comment_support_' . $comment_id));
            $this->redis->del(md5('comment_report_' . $comment_id));
            $this->redis->del(md5('comment_dislike_' . $comment_id));
        }
    }

    /**
     * 参数设置数据缓存
     *
     * @param int $account_id
     *            账户群组id
     * @return null
     */
    private function get_paramers_redis($account_id)
    {
        $FunctionCommon = new \DwComment\Library\FunctionCommon();
        $account = $this->get_auth_account_redis();
        if (! empty($account[$account_id])) {
            $pid = $account[$account_id]['pid'];
            if ($pid == 0) {
                $pid = $account_id;
            }
            $redis = $this->redis->get(md5($pid . 'parameter'));
            $redis = json_decode($redis, true);
            if (! empty($redis)) {
                return $redis;
            } else {
                $redis_name = $pid . 'parameter';
                $sql = 'select id,parameter_report_num,parameter_report_brush,parameter_account_id,parameter_account_pid,parameter_operation_id,parameter_time from dw_parameter where parameter_account_pid=' . $pid . ' limit 1';
                $result = $this->db->query($sql);
                $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
                $this->db->getSQLStatement();
                $redis = $result->fetchAll();
                $redis_name = $pid . 'parameter';
                if (! empty($redis)) {
                    $value = @json_encode($redis[0]);
                    $this->redis->set(md5($redis_name), $value);
                    return $redis[0];
                } else {
                    $value = '';
                    $this->redis->set(md5($redis_name), '');
                    return $value;
                }
            }
        } else {
            return $this->Exception(self::model_account, self::error_code15, self::error_code15_msg);
        }
    }

    /**
     * 获取评论踩缓存
     *
     * @param int $id
     *            评论id
     * @return unknown[]
     */
    private function get_comment_dislike_redis($id)
    {
        $redis = $this->redis->get(md5('comment_dislike_' . $id));
        $redis = json_decode($redis, true);
        if (! empty($redis)) {return $redis;}
        return $this->update_comment_dislike_redis($id);
    }

    /**
     * 更新评论踩缓存
     *
     * @param int $id
     *            评论id
     * @return unknown[]
     */
    private function update_comment_dislike_redis($id)
    {
        $Tread = Tread::find([
            'comment_id = :comment_id:',
            'bind' => [
                'comment_id' => $id
            ]
        ])->toArray();
        $redis = [];
        if (! empty($Tread)) {
            $i = 0;
            foreach ($Tread as $key => $v) {
                $redis['uid'][$v['user_id']] = $v['user_id'];
                $i ++;
            }
            $redis['count'] = $i;
        }
        $this->redis->set(md5('comment_dislike_' . $id), json_encode($redis));
        return $redis;
    }

    /**
     * 获取评论踩缓存数量
     *
     * @param int $comment_id
     *            评论id
     * @param $user_id 用户id            
     * @return unknown[]
     */
    private function comment_dislike_redis_byuser($comment_id, $user_id = null)
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

    /**
     * 获取评论赞缓存数量
     *
     * @param int $comment_id
     *            评论id
     * @param $user_id 用户id            
     * @return unknown[]
     */
    private function comment_support_redis_byuser($comment_id, $user_id = null)
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

    /**
     * 获取评论赞缓存
     *
     * @param int $id
     *            评论id
     * @return unknown[]
     */
    private function get_comment_support_redis($id)
    {
        $redis = $this->redis->get(md5('comment_support_' . $id));
        $redis = json_decode($redis, true);
        if (! empty($redis)) {return $redis;}
        return $this->update_comment_support_redis($id);
    }

    /**
     * 更新评论zan（顶）缓存
     *
     * @param int $id
     *            评论id
     * @return unknown[]
     */
    private function update_message_template_redis()
    {
        $Notify_Category = NotifyCategory::find()->toArray();
        $redis = [];
        if (! empty($Notify_Category)) {
            foreach ($Notify_Category as $key => $v) {
                $redis[$v['name']] = $v;
            }
        }
        $this->redis->set(md5('notify_category'), json_encode($redis));
        return $redis;
    }

    /**
     * 更新评论zan（顶）缓存
     *
     * @param int $id
     *            评论id
     * @return unknown[]
     */
    private function update_comment_support_redis($id)
    {
        $Support = Support::find([
            'comment_id = :comment_id:',
            'bind' => [
                'comment_id' => $id
            ]
        ])->toArray();
        $redis = [];
        if (! empty($Support)) {
            $i = 0;
            foreach ($Support as $key => $v) {
                $redis['uid'][$v['user_id']] = $v['user_id'];
                $i ++;
            }
            $redis['count'] = $i;
        }
        $this->redis->set(md5('comment_support_' . $id), json_encode($redis));
        return $redis;
    }

    /**
     * 更新评论信息缓存
     *
     * @param
     *            int 评论的id
     * @return array
     */
    private function get_comment_redis($id)
    {
        $redis = $this->redis->get('comment_stat_' . $id);
        if (! empty($redis)) {return json_decode($redis, true);}
        return $this->update_comment_redis($id);
    }

    /**
     * 更新评论信息缓存
     *
     * @param int $id
     *            评论id
     * @return unknown[]
     */
    private function update_comment_redis($id)
    {
        $comment = Comment::find([
            'id = :id:',
            'bind' => [
                'id' => $id
            ]
        ])->toArray();
        if (! empty($comment)) {
            $this->redis->set('comment_stat_' . $id, json_encode($comment[0]));
            return $comment[0];
        } else {
            return '';
        }
    }

    /**
     * 更新评论数缓存
     *
     * @param string $comment_url
     *            评论文章url
     * @param string $action
     *            评论数加减操作
     * @param int $count
     *            评论数加减的个数
     */
    private function update_comment_count_redis($comment_url, $action = '+', $count = 1)
    {
        // 评论数缓存
        $redis_count = $this->redis->get($comment_url . '_count');
        if (! empty($redis_count)) {
            if ($action == '+') {
                $redis_count = $redis_count + $count;
            } else {
                $redis_count = $redis_count - $count;
            }
        } else {
            $redis_count = Comment::count([
                'conditions' => 'comment_url = :comment_url:',
                'bind' => [
                    'comment_url' => $comment_url
                ]
            ]);
        }
        $this->redis->set($comment_url . '_count', $redis_count);
        return $redis_count;
    }

    /**
     * 判断用户是否在黑名单
     *
     * @param $comment_user_id 用户uid            
     * @return true 未在黑名单
     * @return http提示状态
     */
    private function check_blacklist($comment_user_id)
    {
        // 检测黑名单
        $redis_blacklist = $this->get_user_blacklist_redis();
        $b_key = md5($comment_user_id);
        if (! empty($comment_user_id) && isset($redis_blacklist[$b_key])) {return true; // return $this->Exception(self::model_blacklist, self::error_code9, self::error_code9_msg);
}
        return false;
    }

    /**
     * 获取黑名单数据缓存
     *
     * @return array 黑名单数据
     */
    private function get_user_blacklist_redis()
    {
        $blacklist_name = RestController::BLACKLIST;
        $redis_blacklist = $this->redis->get($blacklist_name);
        $redis_blacklist = json_decode($redis_blacklist, true);
        if (! empty($redis_blacklist)) {
            return $redis_blacklist;
        } else {
            $sql = 'select id,blacklist_uid,blacklist_action_uid,blacklist_account_pid,blacklist_account_id,blacklist_level,blacklist_reason,blacklist_create,blacklist_update from dw_blacklist';
            $result = $this->db->query($sql);
            $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $redis = $result->fetchAll();
            if (! empty($redis)) {
                foreach ($redis as $s_key => $s_v) {
                    $key = md5($s_v['blacklist_uid']);
                    $a2[$key] = $s_v;
                }
                $value = @json_encode($a2);
                $this->redis->set($blacklist_name, $value);
                return $a2;
            } else {
                $value = '';
                $this->redis->set($blacklist_name, '');
                return array();
            }
        }
    }

    /**
     * 获取消息模板缓存
     *
     * @return unknown[]
     */
    private function get_message_template_redis()
    {
        $redis = $this->redis->get(md5('notify_category'));
        $redis = json_decode($redis, true);
        if (! empty($redis)) {return $redis;}
        return $this->update_message_template_redis();
    }

    /**
     * 表单设定项缓存
     *
     * @return unknown[]
     */
    private function get_fourm_item_redis()
    {
        $redis = $this->redis->get(RestController::FOURM_ITEM);
        $redis = json_decode($redis, true);
        if (! empty($redis)) {
            return $redis;
        } else {
            $sql = 'SELECT id,fourm_item_title,fourm_item_idtype,fourm_item_tag_type,fourm_item_content,fourm_item_is_ver,fourm_item_account,fourm_item_stats,fourm_item_time FROM dw_fourm_category_item';
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
                    $sql2 = 'SELECT id,item_id,item_idtype,item_tag_type,item_tag_name,item_tag_score,item_tag_sort FROM dw_fourm_category_item_ext where item_id = ' . $s_v['id'] . ' order by item_tag_sort asc';
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
                $this->redis->set(RestController::FOURM_ITEM, $value);
                return $a2;
            } else {
                $value = '';
                $this->redis->set(RestController::FOURM_ITEM, '');
                return $value;
            }
        }
    }

    /**
     * 评论表单类型缓存
     *
     * @return array
     */
    private function get_fourm_category_redis()
    {
        $fourm_category = $this->redis->get(RestController::FOURM_CATEGORY);
        $fourm_category = json_decode($fourm_category, true);
        if (! empty($fourm_category)) {
            return $fourm_category;
        } else {
            $sql = 'select id,fourm_title,fourm_idtype_id,fourm_order,fourm_meth,fourm_pess,fourm_number,fourm_reply,fourm_anonymous,fourm_dateline,fourm_actions_uid,fourm_actions_ip,fourm_account from dw_fourm_category';
            $result = $this->db->query($sql);
            $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            $this->db->getSQLStatement();
            $redis = $result->fetchAll();
            if (! empty($redis)) {
                foreach ($redis as $s_key => $s_v) {
                    $a2[$s_v['id']]['id'] = $s_v['id'];
                    $a2[$s_v['id']]['fourm_title'] = $s_v['fourm_title'];
                    $a2[$s_v['id']]['fourm_idtype_id'] = $s_v['fourm_idtype_id'];
                    $a2[$s_v['id']]['fourm_order'] = $s_v['fourm_order'];
                    $a2[$s_v['id']]['fourm_meth'] = $s_v['fourm_meth'];
                    $a2[$s_v['id']]['fourm_pess'] = $s_v['fourm_pess'];
                    $a2[$s_v['id']]['fourm_number'] = $s_v['fourm_number'];
                    $a2[$s_v['id']]['fourm_reply'] = $s_v['fourm_reply'];
                    $a2[$s_v['id']]['fourm_anonymous'] = $s_v['fourm_anonymous'];
                    $a2[$s_v['id']]['fourm_account'] = $s_v['fourm_account'];
                }
                $value = @json_encode($a2);
                $redis = $this->redis->set(RestController::FOURM_CATEGORY, $value);
                return $a2;
            } else {
                return array();
            }
        }
    }

    /**
     * 获取敏感词缓存
     *
     * @param int $account_id
     *            后台账户群组id
     * @return unknown[]
     */
    private function get_sensitive_redis($account_id)
    {
        $_pid = new \DwComment\Library\FunctionCommon();
        // 账户cache
        $pid = $this->get_account_pid($account_id);
        if ($pid == false) {return $this->Exception(self::model_account, self::error_code15, self::error_code15_msg);}
        $zhu_redis = $zi_redis = array();
        // 主账户
        $redis_name = '0_' . $pid . '_senstive';
        $zhu_redis = $this->redis->get(md5($redis_name));
        $zhu_redis = json_decode($zhu_redis, true);
        if (empty($zhu_redis)) {
            // 更新缓存
            $zhu_redis = $this->update_sensitive_redis($account_id, $pid);
        }
        if ($pid != $account_id) {
            // 子账户
            $redis_name = $pid . '_' . $account_id . '_senstive';
            $zi_redis = $this->redis->get(md5($redis_name));
            $zi_redis = json_decode($zi_redis, true);
            if (empty($zi_redis)) {
                // 更新缓存
                $zi_redis = $this->update_sensitive_redis($account_id, $pid);
            }
        }
        return array_merge($zhu_redis, $zi_redis);
    }

    /**
     * 更新敏感词缓存
     *
     * @param int $account_id
     *            后台账户群组id
     * @param int $pid
     *            后台账户群组的父id
     */
    private function update_sensitive_redis($account_id, $pid)
    {
        if ($account_id == $pid) {
            // 主账户
            $redis_name = '0_' . $pid . '_senstive';
        } else {
            // 子账户
            $redis_name = $pid . '_' . $account_id . '_senstive';
        }
        $sql = 'select id,sensitive_level_id,sensitive_name,sensitive_replace,sensitive_action,sensitive_operator,sensitive_account,sensitive_account_pid,sensitive_time from dw_sensitive where sensitive_account =' . $account_id;
        $result = $this->db->query($sql);
        $result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
        $this->db->getSQLStatement();
        $redisdata = $result->fetchAll();
        $a3 = $redisdata = '';
        if (! empty($redisdata)) {
            foreach ($redisdata as $s_key2 => $s_v2) {
                $a3[md5($s_v2['sensitive_name'])] = $s_v2;
            }
            $redisdata = @json_encode($a3);
        }
        $this->redis->set(md5($redis_name), $redisdata);
        return $a3;
    }

    
    /**
     * 获取账户的父id
     *
     * @param int $account_id
     *            账户id
     * @return unknown[]
     */
    private function get_account_pid ($account_id) {
    	$account = $this->get_auth_account_redis();
    	
    	if (isset($account[$account_id])) {
    		$pid = $account[$account_id]['pid'];
    		if ($pid == 0) {
    			$pid = $account_id;
    		}
    		return $pid;
    	} else {
    		return false;
    	}
    }
    

    // 获取账户组数据缓存
    private function get_auth_account_redis () {
    	$account_list = $this->redis->get(md5('account'));
    	$account_list = json_decode($account_list, true);
    	if (! empty($account_list)) {
    		return $account_list;
    	} else {
    		$sql = 'select * from dw_auth_account';
    		$result = $this->db->query($sql);
    		$result->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
    		$this->db->getSQLStatement();
    		$redis = $result->fetchAll();
    
    		if (! empty($redis)) {
    
    			foreach ($redis as $s_key => $s_v) {
    				$key = md5($s_v['id']);
    				$a2[$s_v['id']] = $s_v;
    			}
    
    			$value = @json_encode($a2);
    			$this->redis->set(md5('account'), $value);
    			return $a2;
    		} else {
    			$value = '';
    			$this->redis->set(md5('account'), '');
    			return array();
    		}
    	}
    }
    /**
     * 获取敏感词缓存
     *
     * @param int $account_id
     *            后台账户群组id
     * @param array $sensitive
     *            检测到的敏感词数组
     * @param string $content
     *            评论的检测内容
     * @return array
     */
    private function check_sensitive_type($account_id, $sensitive, $content)
    {
        $all_sensitive = $this->get_sensitive_redis($account_id);
        $array = array();
        if (! empty($sensitive)) {
            foreach ($sensitive as $key => $v) {
                if (isset($all_sensitive[md5($v)])) {
                    if ($all_sensitive[md5($v)]['sensitive_action'] == 1) {
                        $array['forbid'][] = $v; // 禁止类
                    } elseif ($all_sensitive[md5($v)]['sensitive_action'] == 2) {
                        $array['review'][] = $v; // 审核类
                    } elseif ($all_sensitive[md5($v)]['sensitive_action'] == 3) {
                        $array['replace'] = $v; // 替换类
                        $content = str_replace($v, $all_sensitive[md5($v)]['sensitive_replace'], $content);
                    }
                }
            }
        }
        $array['content'] = $content;
        return $array;
    }
    

    /**
     *
     * {@inheritdoc} string $content 检测内容
     *               int $account_id 评论表单所属账户id
     */
    private  function sensitive_check ($content, $account_id = null) {
    	
    	$pid = $this->get_account_pid($account_id);
    	if($pid == false){
    		return 'P0015';
    	}
    	$type = '0_' . $pid . '_sensitive';
    	if ($pid != $account_id) {
    		$type .= ',' . $pid . '_' . $account_id . '_sensitive';
    	}
    	$type = trim($type, ',');
    	// 配置文件
    	$url = RestController::SENSITIVE_URI;
    	$post_data = $content.'###'.$type;
    	try {
    		$this->client->connect();
    		$this->client->send($post_data);
    		$tmp = $this->client->recv();
    		return json_decode($tmp, true);
    	} catch (\Exception $e) {
    		return 'P0017';
    	}
    	
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::sensitive_comment_info()
     */
    public function sensitive_comment_info()
    {
        // TODO Auto-generated method stub
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::get_users_comment_status_info()
     */
    public function get_users_comment_status_info()
    {
        // TODO Auto-generated method stub
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::check_comment_status_info()
     */
    public function check_comment_status_info()
    {
        // TODO Auto-generated method stub
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Psr\Http\Message\CommentInterface::tags_cms_to_comment_info()
     */
    public function tags_cms_to_comment_info()
    {
        // TODO Auto-generated method stub
    }
}
