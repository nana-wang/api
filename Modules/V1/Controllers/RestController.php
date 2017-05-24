<?php
namespace DwComment\Modules\V1\Controllers;

use DwComment\Exceptions\HttpException;

class RestController extends BaseController
{
    // 接口域名
    const DOMAIN_HOST = 'http://api.img.dwnews.com';
    // 过期时间
    const EXPIRES = '3';
    // 敏感词地址
    // const SENSITIVE_URI = 'http://focus.dwnews.com:9502/sensitive/filter.php?jsoncallback=%3F';
    const SENSITIVE_URI = 'http://172.31.28.79:9502/sensitive/filter.php?jsoncallback=%3F';
    //const SENSITIVE_URI = 'http://172.31.28.79:9502/sensitive/filter.php?jsoncallback=%3F';
    // csrf
    const GET_CSRF = 'get_comment_csrf_token';
    // 匿名_UID
    const ANONYMOUS = 'dw_999999';
    // 防注入
    const GET_SECURITY_CODE = 'get_security_code';
    
    // 黑名单缓存名称
    const BLACKLIST = 'blacklist';
    // 评论表单缓存名称
    const FOURM_CATEGORY = 'fourm_category';
    // 表单项设定缓存名称
    const FOURM_ITEM = 'fourm_item';
    // 用户评论-灌数据-缓存前缀
    const COMMENT_IRRIGATION = 'comment_irrigation_';
    // 表单设定项-图片类
    const FOURM_ITEM_IMG = 2;
    // 表单设定项-文字类
    const FOURM_ITEM_WORD = 1;
    // 表单设定项-标签类
    const FOURM_ITEM_TAG = 3;
    
    // 评分表单类型-标签类
    const FOURM_ITEM_COMMENT_SCORE = 3;
    
    // 文字表单类型
    const FOURM_ITEM_COMMENT_WORD = 1;
    
    // 文章评论首页缓存条数
    const ARTICLE_COMMENT_REDIS_NUM = 50;
    // 主评标志
    const COMMENT_PARENT_FLG = 0;
    // 主评需审批
    const COMMENT_METH_STATS = 1;
    // 评论一人一条标志
    const FOURM_NUMBER_STATS = 1;
    // 不可匿名评论标志
    const FOURM_ANONYMOUS_STATS = 1;
    // 可以回复评论标志
    const FOURM_REPLY_STATS = 1;
    // 不可编辑评论标志
    const FOURM_PESS_STATS = 0;
    // 默认一审送审次数
    const SEND_NUM1 = 2;
    // 默认二审送审次数
    const SEND_NUM2 = 5;
    // 评论发布状态
    const COMMENT_PUBLIC = 1;
    // 评论审核状态
    const COMMENT_AUDITING = 2;
    // 评论隐藏状态
    const COMMENT_HIDDEN = 3;
    // 评论人工送审状态
    const COMMENT_AUDITING_TRIAL = 4;
    // 评论敏感词送审状态
    const COMMENT_SENSITIVE = 5;
    // 评论举报送审状态
    const COMMENT_REPORT = 6;
    // 评论用户冻结状态
    const COMMENT_FROZEN = 7;
    // 评论锁定状态
    const COMMENT_LOCK = 8;
    // 黑名单1级
    const BLACK_LEVEL1 = 1;
    // 黑名单2级
    const BLACK_LEVEL2 = 2;
    // 黑名单3级
    const BLACK_LEVEL3 = 3;
    
    // 评论回复消息模版
    const RREPLY_TEMPLATE = 1;
    // 留言消息模版
    const SUGGEST_TEMPLATE = 2;
    // 评论消息模版
    const COMMENT_TEMPLATE = 3;
    // 收藏消息模版
    const FAVOURITE_TEMPLATE = 4;
    // 赞消息模版
    const UP_ARTICLE_TEMPLATE = 5;
    // 评论发布状态
    const COMMENT_PUBLIC_STATS = 1;

    protected $isSearch = false;

    protected $isPartial = false;

    /**
     * Set when there is a 'limit' query parameter
     *
     * @var integer
     */
    protected $limit = null;

    /**
     * Set when there is an 'offset' query parameter
     *
     * @var integer
     */
    protected $offset = null;

    /**
     * Array of fields requested to be searched against
     *
     * @var array
     */
    protected $searchFields = null;

    /**
     * Array of fields requested to be returned
     *
     * @var array
     */
    protected $partialFields = null;
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

    const success_code_msg = '操作成功';

    const error_code1_msg = '参数类型错误，或者缺少必要的参数！';

    const error_code2_msg = '操作过程异常，请联系管理员';

    const error_code3_msg = '无数据,或者数据不存';

    const error_code4_msg = '提交过于频繁，请稍后再试';

    const error_code5_msg = '评论表单不存在';

    const error_code6_msg = '此表单为一人一评论，您已经评论过';

    const error_code7_msg = '此表单不可匿名评论';

    const error_code8_msg = '此表单不可回复';

    const error_code9_msg = '用户在黑名单内，无法操作';

    const error_code10_msg = '您无权限操作';

    const error_code11_msg = '此表单不可编辑';

    const error_code12_msg = '您已经点过赞了';

    const error_code13_msg = '取消失败,您未曾点赞';

    const error_code14_msg = '此用户已经在黑名单内，不能重复添加';

    const error_code15_msg = '账户数据异常，账户被停用或者删除';

    const error_code16_msg = '数据未处于发布状态，无法编辑';

    const error_code17_msg = '敏感词接口异常';

    const error_code18_msg = '内容中含有禁止类的敏感词';

    const error_code19_msg = '编辑表单与原数据表单不符';

    const error_code20_msg = '评分异常，数据不存在';

    const error_code21_msg = '您已经举报过，不能重复举报';

    const error_code22_msg = '评论需要审核，审核通过后才能正常显示';

    const error_code23_msg = '评论内容中包含审核类敏感词，审核通过后才能正常显示';

    const error_code24_msg = '您已经踩过了';

    const error_code25_msg = '取消失败,您未曾点踩';

    const model_illegal = '请求非法';

    const model_comment_list = '评论列表';

    const model_comment_add = '评论添加';

    const model_comment_update = '评论编辑';

    const model_comment_del = '评论删除';

    const model_comment_support = '评论点赞';

    const model_comment_dislike = '评论点踩';

    const model_comment_up_down = '评论顶踩';

    const model_comment_report = '评论举报';

    const model_comment_sensitive = '敏感词';

    const model_comment_emotion = '表情包';

    const model_fourm_item_tag = '表单标签';

    const model_blacklist = '用户黑名单';

    const model_comment_score = '综合评分';

    const model_account = '账户信息';

    const model_queue = '消息';

    const model_read_notify = '修改消息状态(已读)';

    const model_user = '用户信息';

    const model_security = '验证码';

    const model_sensitive = '请求非法';

    const success_code = 'P0000';

    const error_code1 = 'P0001';

    const error_code2 = 'P0002';

    const error_code3 = 'P0003';

    const error_code4 = 'P0004';

    const error_code5 = 'P0005';

    const error_code6 = 'P0006';

    const error_code7 = 'P0007';

    const error_code8 = 'P0008';

    const error_code9 = 'P0009';

    const error_code10 = 'P0010';

    const error_code11 = 'P0011';

    const error_code12 = 'P0012';

    const error_code13 = 'P0013';

    const error_code14 = 'P0014';

    const error_code15 = 'P0015';

    const error_code16 = 'P0016';

    const error_code17 = 'P0017';

    const error_code18 = 'P0018';

    const error_code19 = 'P0019';

    const error_code20 = 'P0020';

    const error_code21 = 'P0021';

    const error_code22 = 'P0022';

    const error_code23 = 'P0023';

    const error_code24 = 'P0024';

    const error_code25 = 'P0025';

    const statusInfo = 'statInfo';

    
    /**
     * Sets which fields may be searched against, and which fields are allowed
     * to be returned in
     * partial responses.
     * This will be overridden in child Controllers that support searching
     * and partial responses.
     *
     * @var array
     */
    protected $allowedFields = [
        'search' => [],
        'partials' => []
    ];

    /**
     * Constructor, calls the parse method for the query string by default.
     *
     * @param boolean $parseQueryString
     *            true Can be set to false if a controller needs to be called
     *            from a different controller, bypassing the $allowedFields
     *            parse
     */
    public function onConstruct($parseQueryString = true)
    {
        if ($parseQueryString) {
            $this->parseRequest($this->allowedFields);
        }
        return;
    }

    /**
     * Parses out the search parameters from a request.
     * Unparsed, they will look like this:
     * (name:Benjamin Framklin,location:Philadelphia)
     * Parsed:
     * ['name'=>'Benjamin Franklin', 'location'=>'Philadelphia']
     *
     * @param string $unparsed
     *            Unparsed search string
     * @return array An array of fieldname=>value search parameters
     */
    protected function parseSearchParameters($unparsed)
    {
        
        // Strip parentheses that come with the request string
        $unparsed = trim($unparsed, '()');
        
        // Now we have an array of "key:value" strings.
        $splitFields = explode(',', $unparsed);
        $mapped = [];
        
        // Split the strings at their colon, set left to key, and right to
        // value.
        foreach ($splitFields as $field) {
            $splitField = explode(':', $field);
            $mapped[$splitField[0]] = $splitField[1];
        }
        
        return $mapped;
    }

    /**
     * Parses out partial fields to return in the response.
     * Unparsed:
     * (id,name,location)
     * Parsed:
     * ['id', 'name', 'location']
     *
     * @param string $unparsed
     *            Un-parsed string of fields to return in partial response
     * @return array Array of fields to return in partial response
     */
    protected function parsePartialFields($unparsed)
    {
        return explode(',', trim($unparsed, '()'));
    }

    /**
     * Main method for parsing a query string.
     * Finds search paramters, partial response fields, limits, and offsets.
     * Sets Controller fields for these variables.
     *
     * @param array $allowedFields
     *            Allowed fields array for search and partials
     * @return boolean Always true if no exception is thrown
     * @throws HttpException If some of the fields requested are not allowed
     */
    protected function parseRequest($allowedFields)
    {
        $request = $this->di->get('request');
        $searchParams = $request->get('q', null, null);
        $fields = $request->get('fields', null, null);
        $this->limit = ($request->get('limit', null, null)) ?: $this->limit;
        $this->offset = ($request->get('offset', null, null)) ?: $this->offset;
        if ($searchParams) {
            $this->isSearch = true;
            $this->searchFields = $this->parseSearchParameters($searchParams);
            
            // This handy snippet determines if searchFields is a strict subset
            // of allowedFields['search']
            if (array_diff(array_keys($this->searchFields), $this->allowedFields['search'])) {throw new HttpException("The fields you specified cannot be searched.", 401, null, [
                    'dev' => 'You requested to search fields that are not available to be searched.',
                    'internalCode' => 'S1000',
                    'more' => ''
                ]); // Could have link to documentation here.
}
        }
        if ($fields) {
            $this->isPartial = true;
            $this->partialFields = $this->parsePartialFields($fields);
            
            // Determines if fields is a strict subset of allowed fields
            if (array_diff($this->partialFields, $this->allowedFields['partials'])) {throw new HttpException("The fields you asked for cannot be returned.", 401, null, [
                    'dev' => 'You requested to return fields that are not available to be returned in partial responses.',
                    'internalCode' => 'P1000',
                    'more' => ''
                ]); // Could have link to documentation here.
}
        }
        
        return true;
    }

    /**
     * Provides a base CORS policy for routes like '/users' that represent a
     * Resource's base url
     * Origin is allowed from all urls.
     * Setting it here using the Origin header from the request
     * allows multiple Origins to be served. It is done this way instead of with
     * a wildcard '*'
     * because wildcard requests are not supported when a request needs
     * credentials.
     *
     * @return true
     */
    public function optionsBase()
    {
        $response = $this->di->get('response');
        $methods = [];
        foreach ([
            'get',
            'post',
            'put',
            'patch',
            'delete'
        ] as $method) {
            if (method_exists($this, $method)) {
                array_push($methods, strtoupper($method));
                if ($method === 'get') {
                    array_push($methods, 'HEAD');
                }
            }
        }
        array_push($methods, 'OPTIONS');
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $methods));
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->setHeader('Access-Control-Allow-Headers', "origin, x-requested-with, content-type");
        $response->setHeader('Access-Control-Max-Age', '86400');
        return true;
    }

    /**
     * Provides a CORS policy for routes like '/users/123' that represent a
     * specific resource
     *
     * @return true
     */
    public function optionsOne()
    {
        $response = $this->di->get('response');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, PUT, PATCH, DELETE, OPTIONS, HEAD');
        $response->setHeader('Access-Control-Allow-Origin', $this->di->get('request')
            ->header('Origin'));
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->setHeader('Access-Control-Allow-Headers', "origin, x-requested-with, content-type");
        $response->setHeader('Access-Control-Max-Age', '86400');
        return true;
    }

    /**
     * Should be called by methods in the controllers that need to output
     * results to the HTTP Response.
     * Ensures that arrays conform to the patterns required by the Response
     * objects.
     *
     * @param array $recordsArray
     *            Array of records to format as return output
     * @return array Output array. If there are records (even 1), every record
     *         will be an array ex: [['id'=>1],['id'=>2]]
     * @throws HttpException If there is a problem with the records
     */
    protected function respond($recordsArray)
    {
        if (! is_array($recordsArray)) {throw new HttpException("An error occurred while retrieving records.", 500, null, array(
                'dev' => 'The records returned were malformed.',
                'applicationCode' => 'RESP1000',
                'more' => ''
            ));}
        if (count($recordsArray) === 0) {return [];}
        return $recordsArray;
    }
}
