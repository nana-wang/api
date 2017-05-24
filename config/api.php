<?php
namespace DwComment\Config;

use DwComment\Modules\V1\Controllers\BaseController;

class Api extends BaseController
{
    // 评论入口
    const INSERT_COMMENT_INFO = 'insert_comment_info';
    // 删除信息
    const DEL_COMMENT_INFO = 'delete_comment_info';
    // 编辑信息
    const EDIT_COMMENT_INFO = 'edit_comment_info';
    // 评论列表信息*
    const GET_COMMENT_INFO = 'get_article_comment_info';
    // 获取个人评论信息信息*
    const GET_PERSONAL_COMMENT_INFO = 'get_personal_comment_info';
    // 取个人评论信息
    const GET_SEARCH_COMMENT_INFO = 'search_comment_info';
    // 点赞信息
    const SUPPORT_COMMENT_INFO = 'support_comment_info';
    // 取消点赞信息
    const UNSUPPORT_COMMENT_INFO = 'unsupport_comment_info';
    
    // 点踩信息
    const DISLIKE_COMMENT_INFO = 'dislike_comment_info';
    // 取消点踩信息
    const UNDISLIKE_COMMENT_INFO = 'undislike_comment_info';
    // 举报信息
    const REPORT_COMMENT_INFO = 'report_comment_info';
    // 举报分类
    const GET_REPORT_CATEGORY = 'get_report_category';
    // 敏感词信息
    const SENSITIVE_COMMENT_INFO = 'sensitive_comment_info';
    // 敏感词检测信息
    const USER_COMMENT_STATUS_INFO = 'get_users_comment_status_info';
    // 用户状态检测信息
    const CHECK_COMMENT_STATUS_INFO = 'check_comment_status_info';
    // 检测评论状态信息
    const TAGS_CMS_TO_COMMENT_INFO = 'tags_cms_to_comment_info';
    // 获取表情包
    const GET_EMOTICON_INFO = 'get_emoticon_info';
    // 获取表单对应的标签
    const GET_FOURM_CATEGORY_ITEM = 'get_fourm_category_item';
    // 获取用户id
    const GET_USER_INFO = 'get_user_info';
    // 添加黑名单
    const INSERT_USER_BLACKLIST = 'insert_user_blacklist';
    // 移除黑名单
    const REMOVE_USER_BLACKLIST = 'remove_user_blacklist';
    // 获取综合评分
    const GET_ARTICLE_COMPOSITE_SCORE = 'get_article_composite_score';
    // 获取消息
    const GET_QUEUE = 'get_queue';
    // 删除消息
    const DEL_QUEUE = 'del_queue';
    // 修改消息状态(已读)
    const READ_NOTIFY = 'read_notify';
    // 获取用户消息
    const USER_NOTIFY_INFO = 'user_notify_info';
    // 获取用户临时信息
    const TEMP_USER_INFO = 'temp_user_info';
    

    private $success_code_msg = '操作成功';

    private $error_code1_msg = '参数类型错误，或者缺少必要的参数！';

    private $error_code2_msg = '操作过程异常，请联系管理员';

    private $error_code3_msg = '无数据,或者数据不存';

    private $error_code4_msg = '提交过于频繁，请稍后再试';

    private $error_code5_msg = '评论表单不存在';

    private $error_code6_msg = '此表单为一人一评论，您已经评论过';

    private $error_code7_msg = '此表单不可匿名评论';

    private $error_code8_msg = '此表单不可回复';

    private $error_code9_msg = '用户在黑名单内，无法操作';

    private $error_code10_msg = '您无权限操作';

    private $error_code11_msg = '此表单不可编辑';

    private $error_code12_msg = '您已经点过赞了';

    private $error_code13_msg = '取消失败,您未曾点赞';

    private $error_code14_msg = '此用户已经在黑名单内，不能重复添加';

    private $error_code15_msg = '账户数据异常，账户被停用或者删除';

    private $error_code16_msg = '数据未处于发布状态，无法编辑';

    private $error_code17_msg = '敏感词接口异常';

    private $error_code18_msg = '内容中含有禁止类的敏感词';

    private $error_code19_msg = '编辑表单与原数据表单不符';

    private $error_code20_msg = '评分异常，数据不存在';

    private $error_code21_msg = '您已经举报过，不能重复举报';

    private $error_code22_msg = '评论需要审核，审核通过后才能正常显示';

    private $error_code23_msg = '评论内容中包含审核类敏感词，审核通过后才能正常显示';

    private $error_code24_msg = '您已经踩过了';

    private $error_code25_msg = '取消失败,您未曾点踩';

    private $model_illegal = '请求非法';

    private $model_comment_list = '评论列表';

    private $model_comment_add = '评论添加';

    private $model_comment_update = '评论编辑';

    private $model_comment_del = '评论删除';

    private $model_comment_support = '评论点赞';

    private $model_comment_dislike = '评论点踩';

    private $model_comment_up_down = '评论顶踩';

    private $model_comment_report = '评论举报';

    private $model_comment_sensitive = '敏感词';

    private $model_comment_emotion = '表情包';

    private $model_fourm_item_tag = '表单标签';

    private $model_blacklist = '用户黑名单';

    private $model_comment_score = '综合评分';

    private $model_account = '账户信息';

    private $model_queue = '消息';

    private $model_read_notify = '修改消息状态(已读)';

    private $model_user = '用户信息';

    private $model_security = '验证码';

    private $model_sensitive = '请求非法';

    private $success_code = 'P0000';

    private $error_code1 = 'P0001';

    private $error_code2 = 'P0002';

    private $error_code3 = 'P0003';

    private $error_code4 = 'P0004';

    private $error_code5 = 'P0005';

    private $error_code6 = 'P0006';

    private $error_code7 = 'P0007';

    private $error_code8 = 'P0008';

    private $error_code9 = 'P0009';

    private $error_code10 = 'P0010';

    private $error_code11 = 'P0011';

    private $error_code12 = 'P0012';

    private $error_code13 = 'P0013';

    private $error_code14 = 'P0014';

    private $error_code15 = 'P0015';

    private $error_code16 = 'P0016';

    private $error_code17 = 'P0017';

    private $error_code18 = 'P0018';

    private $error_code19 = 'P0019';

    private $error_code20 = 'P0020';

    private $error_code21 = 'P0021';

    private $error_code22 = 'P0022';

    private $error_code23 = 'P0023';

    private $error_code24 = 'P0024';

    private $error_code25 = 'P0025';

    function __construct()
    {}

    private $statusInfo = 'statInfo';

    public function getStatusInfo()
    {
        if ($this->redis->exists($this->statusInfo)) {
            $data = unserialize($this->redis->get($this->statusInfo));
            return self::Arr2Obj($data);
        } else {
            $this->redis->set($this->statusInfo, serialize(get_class_vars(__CLASS__)));
            $data = unserialize($this->redis->get($this->statusInfo));
            return self::Arr2Obj($data);
        }
    }

    private static function Arr2Obj($data)
    {
        if (is_array($data)) {
            $obj = new \StdClass();
            foreach ($data as $key => $val) {
                $obj->$key = $val;
            }
        } else {
            $obj = $data;
        }
        return $obj;
    }
}