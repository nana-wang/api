<?php
namespace Psr\Http\Message;

interface CommentInterface {


    public function __toString ();

    public function Exception ($dev, $internalCode, $more);
    // Add Coment Data 添加评论
    public function insert_comment_info ();
    // Comment List 评论列表信息
    public function get_article_comment_info ();
    // Personal Comment List 个人评论信息
    public function get_personal_comment_info ();
    // Search Comment info 检索评论信息
    public function search_comment_info ();
    // Edit Comment info 编辑评论信息
    public function edit_comment_info ();
    // Support Comment info 点赞信息
    public function support_comment_info ();
    // Up Down info顶踩信息
    // public function up_down_comment ();
    // 删除评论信息
    public function delete_comment_info ();
    // 取消点赞信息
    public function unsupport_comment_info ();
    // 点踩信息
    public function dislike_comment_info ();
    // 取消点踩信息
    public function undislike_comment_info ();
    // 举报信息
    public function report_comment_info ();
    // 举报分类
    public function get_report_category ();
    // 敏感词信息
    public function sensitive_comment_info ();
    // 敏感词检测信息
    public function get_users_comment_status_info ();
    // 用户状态检测信息
    public function check_comment_status_info ();
    // Cms 检测评论5.0 状态信息
    public function tags_cms_to_comment_info ();
    // 获取表情包
    public function get_emoticon_info ();
    // 获取表单对应的标签
    public function get_fourm_category_item ();
    // 获取用户UID
    public function get_user_info ();
    // 添加黑名单
    public function insert_user_blacklist ();
    // 移除黑名单
    public function remove_user_blacklist ();
    // 获取综合评分
    public function get_article_composite_score ();
    // 获取消息队列
    public function get_queue ();
    // 删除消息队列
    public function del_queue ();
    // 修改消息状态(已读)
    public function read_notify ();
    // 获取用户消息
    public function user_notify_info ();
}
