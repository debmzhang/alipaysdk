<?php
/**
 * @description alipay 扩展
 */

require __DIR__ . DIRECTORY_SEPARATOR . 'aop' . DIRECTORY_SEPARATOR . 'AopClient.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'buildermodel' . DIRECTORY_SEPARATOR . 'AlipayTradeWapPayContentBuilder.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'aop' . DIRECTORY_SEPARATOR . 'request' . DIRECTORY_SEPARATOR . 'AlipayTradeWapPayRequest.php';

class Alipay
{
    public $app_id;
    public $sign_type;
    public $alipay_public_key_file;
    public $merchant_public_key_file;
    public $merchant_private_key_file;
    public $charset;
    public $gateway_url;
    public $format;
    public $notify_url;
    public $return_url;
    public $subject;
    public $body;
    public $timeout_express;
    public $debug;

    /**
     * __construct
     */
    public function __construct($config = array())
    {
        if (is_array($config)) {
            foreach ((array) $config as $key => $val) {
                $this->$key = $val;
            }
        }
    }

    /**
     * 支付宝 pay
     *
     * @param string $out_trade_no 商户订单号
     * @param string $total_amount 支付总金额 (单位/元)
     * @param string $payway 支付方式 wap/sdk
     */
    public function pay($out_trade_no = '', $total_amount = 0, $payway = 'wap')
    {
        // 强转为整型
        $totalAmount = (int) $total_amount;
        if (!$totalAmount) {
            return false;
        }
        // biz content builder
        $payRequestBuilder = new AlipayTradeWapPayContentBuilder();
        $payRequestBuilder->setBody($this->body);
        $payRequestBuilder->setSubject($this->subject);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        $payRequestBuilder->setTotalAmount($totalAmount);
        $payRequestBuilder->setTimeExpress($this->timeout_express);
        // biz content
        $bizContent = $payRequestBuilder->getBizContent();
        // alipay wappay request
        $request = new AlipayTradeWapPayRequest();
        $request->setNotifyUrl($this->notify_url);
        $request->setReturnUrl($this->return_url);
        $request->setBizContent($bizContent);
        // 调用充值 api
        // 解析 key
        $rsaPrivateKey = $this->_getContent($this->merchant_private_key_file, 'pri');
        $alipayrsaPublicKey = $this->_getContent($this->alipay_public_key_file);
        $aop = new AopClient();
        $aop->gatewayUrl = $this->gateway_url;
        $aop->appId = $this->app_id;
        $aop->rsaPrivateKey = $rsaPrivateKey;
        $aop->alipayrsaPublicKey = $alipayrsaPublicKey;
        $aop->apiVersion = '1.0';
        $aop->postCharset = $this->charset;
        $aop->format = $this->format;
        $aop->signType = $this->sign_type;
        // 开启页面信息输出
        // $aop->debugInfo = true;
        if ('wap' == $payway) {
            $result = $aop->pageExecute($request, 'post');
            // file_put_contents('/tmp/zlog.log', $result . "\n", FILE_APPEND);
            echo $result;
        } elseif ('sdk' == $payway) {
            $result = $aop->sdkExecute($request);
            return $result;
        }
    }

    /**
     * 验签方法
     * @param $params 验签支付宝返回的信息，使用支付宝公钥。
     * @return boolean
     */
    public function check($params = array())
    {
        if (!$params || !is_array($params)) {
            return false;
        }
        $alipayrsaPublicKey = $this->_getContent($this->alipay_public_key_file);
        $aop = new AopClient();
        $aop->alipayrsaPublicKey = $alipayrsaPublicKey;
        $result = $aop->rsaCheckV1($params, $alipayrsaPublicKey, $this->sign_type);
        return $result;
    }

    /**
     * 私有方法, 获取 公/私 钥文件中的值
     *
     * @param string $file 文件路径
     * @param string $type 替换类型公钥 pub 私钥 pri
     */
    private function _getContent($file = '', $type = 'pub')
    {
        if (is_file($file)) {
            $search0 = array(
                "\r",
                "\n",
            );
            if ('pub' == $type) {
                $search1 = array(
                    '-----BEGIN PUBLIC KEY-----',
                    '-----END PUBLIC KEY-----',
                );
            }
            if ('pri' == $type) {
                $search1 = array(
                    '-----BEGIN RSA PRIVATE KEY-----',
                    '-----END RSA PRIVATE KEY-----',
                );
            }
            $search = array_merge($search0, $search1);
            $content = file_get_contents($file);
            return str_replace($search, '', $content);
        }
        return $file;
    }

}
