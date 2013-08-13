<?php /*@charset "utf-8"*/
/**
 *********************************************************************
 * AmazonAPI v1.0
 * @file   pw_amazon_api.php
 * @date   2013-03-12 20:48:43 (Tuesday)
 *********************************************************************/

require(dirname(__FILE__) . '/libpw_amazon_critical_section.php');
require(dirname(__FILE__) . '/libpw_amazon_cache.php');
require(dirname(__FILE__) . '/libpw_amazon_isbn.php');

/**
 *====================================================================
 * 本体
 *===================================================================*/
if(!class_exists('libpw_Amazon_API')){
    class libpw_Amazon_API {
        //---------------------------------------------------------------------
        const VERSION        = '2011-08-01';
        const REQUEST_WAIT   = 2.0;
        const CACHE_LIFETIME = 3600;

        //---------------------------------------------------------------------
        const TRAKING_ID_JP     = 'amazon_co_jp';
        const TRAKING_ID_US     = 'amazon_com';
        const TRAKING_ID_UK     = 'amazon_co_uk';
        const TRAKING_ID_DE     = 'amazon_de';
        const TRAKING_ID_FR     = 'amazon_fr';
        const TRAKING_ID_CA     = 'amazon_ca';
        const ACCESS_KEY        = 'access_key';
        const PRIVATE_KEY       = 'private_key';
        const LOCKFILE          = 'lockfile';
        const CACHE_DIR         = 'cache_dir';

        //---------------------------------------------------------------------
        const LOCKFILE_DEFAULT  = 'pwamzapi_lockfile';
        const CACHE_DIR_DEFAULT = '/cache/';

        //---------------------------------------------------------------------
        static private $ENDPOINT = array(
            libpw_Amazon_API::TRAKING_ID_JP      => 'http://ecs.amazonaws.jp/onca/xml',
            libpw_Amazon_API::TRAKING_ID_US      => 'http://ecs.amazonaws.com/onca/xml',
            libpw_Amazon_API::TRAKING_ID_UK      => 'http://ecs.amazonaws.co.uk/onca/xml',
            libpw_Amazon_API::TRAKING_ID_DE      => 'http://ecs.amazonaws.de/onca/xml',
            libpw_Amazon_API::TRAKING_ID_FR      => 'http://ecs.amazonaws.fr/onca/xml',
            libpw_Amazon_API::TRAKING_ID_CA      => 'http://ecs.amazonaws.ca/onca/xml',
            /*
            libpw_Amazon_API::TRAKING_ID_JP      => 'http://webservices.amazon.jp/onca/xml',
            libpw_Amazon_API::TRAKING_ID_US      => 'http://webservices.amazon.com/onca/xml',
            libpw_Amazon_API::TRAKING_ID_UK      => 'http://webservices.amazon.co.uk/onca/xml',
            libpw_Amazon_API::TRAKING_ID_DE      => 'http://webservices.amazon.de/onca/xml',
            libpw_Amazon_API::TRAKING_ID_FR      => 'http://webservices.amazon.fr/onca/xml',
            libpw_Amazon_API::TRAKING_ID_CA      => 'http://webservices.amazon.ca/onca/xml',
             */
            );
        static private $ENDPOINTS = array(
            libpw_Amazon_API::TRAKING_ID_JP      => 'https://aws.amazonaws.jp/onca/xml',
            libpw_Amazon_API::TRAKING_ID_US      => 'https://aws.amazonaws.com/onca/xml',
            libpw_Amazon_API::TRAKING_ID_UK      => 'https://aws.amazonaws.co.uk/onca/xml',
            libpw_Amazon_API::TRAKING_ID_DE      => 'https://aws.amazonaws.de/onca/xml',
            libpw_Amazon_API::TRAKING_ID_FR      => 'https://aws.amazonaws.fr/onca/xml',
            libpw_Amazon_API::TRAKING_ID_CA      => 'https://aws.amazonaws.ca/onca/xml',
            /*
            libpw_Amazon_API::TRAKING_ID_JP      => 'https://webservices.amazon.jp/onca/xml',
            libpw_Amazon_API::TRAKING_ID_US      => 'https://webservices.amazon.com/onca/xml',
            libpw_Amazon_API::TRAKING_ID_UK      => 'https://webservices.amazon.co.uk/onca/xml',
            libpw_Amazon_API::TRAKING_ID_DE      => 'https://webservices.amazon.de/onca/xml',
            libpw_Amazon_API::TRAKING_ID_FR      => 'https://webservices.amazon.fr/onca/xml',
            libpw_Amazon_API::TRAKING_ID_CA      => 'https://webservices.amazon.ca/onca/xml',
             */
            );

        //---------------------------------------------------------------------
        private $opt;
        private $critical;
        private $cache;

        /**
         *====================================================================
         * construct
         *===================================================================*/
        public function __construct($opt = array()) {
            $this->opt = wp_parse_args($opt,
                                       array(libpw_Amazon_API::TRAKING_ID_JP   => '',
                                             libpw_Amazon_API::TRAKING_ID_US   => '',
                                             libpw_Amazon_API::TRAKING_ID_UK   => '',
                                             libpw_Amazon_API::TRAKING_ID_DE   => '',
                                             libpw_Amazon_API::TRAKING_ID_FR   => '',
                                             libpw_Amazon_API::TRAKING_ID_CA   => '',
                                             libpw_Amazon_API::ACCESS_KEY      => '',
                                             libpw_Amazon_API::PRIVATE_KEY     => '',
                                             libpw_Amazon_API::LOCKFILE        => libpw_Amazon_API::LOCKFILE_DEFAULT,
                                             libpw_Amazon_API::CACHE_DIR       => libpw_Amazon_API::CACHE_DIR_DEFAULT,
                                             )
                                       );

            $this->critical = new libpw_Amazon_Critical_Section($this->opt[libpw_Amazon_API::LOCKFILE]);
            $this->cache    = new libpw_Amazon_Cache(self::CACHE_LIFETIME, $this->opt[libpw_Amazon_API::CACHE_DIR]);
        }

        /**
         *--------------------------------------------------------------------
         * domainをmarketplace_domainに変換
         *-------------------------------------------------------------------*/
        private function domain_to_marketplace_domain($domain) {
            $domain_to_marketplace_domain = array('javari_jp'   => 'www.javari.jp',
                                                  );
            if(isset($domain_to_marketplace_domain[$domain])){
                return $domain_to_marketplace_domain[$domain];
            }
            return null;
        }

        /**
         *--------------------------------------------------------------------
         * domainをaid用domainに変換
         *-------------------------------------------------------------------*/
        private function domain_to_aid_domain($domain) {
            $domain_to_aid_domain = array('javari_jp'   => 'amazon.co.jp',
                                          );
            if(isset($domain_to_aid_domain[$domain])){
                return $domain_to_aid_domain[$domain];
            }
            return $domain;
        }

        /**
         *--------------------------------------------------------------------
         * 標準パラメータの取得
         *-------------------------------------------------------------------*/
        private function get_common_parameter($domain) {
            $param = array('Service'            => 'AWSECommerceService',
                           'Version'            => self::VERSION,
                           'Timestamp'          => gmdate("Y-m-d\TH:i:s\Z"),
                           'AWSAccessKeyId'     => $this->opt[self::ACCESS_KEY],
                           'ContentType'        => 'text/xml',
                           'MerchantId'         => 'All',
                           );

            $marketplace_domain = $this->domain_to_marketplace_domain($domain);
            if(!is_null($marketplace_domain)){
                $param['MarketplaceDomain'] = $marketplace_domain;
            }

            $aid_domain = $this->domain_to_aid_domain($domain);
            if($this->opt[$aid_domain] != ''){
                $param['AssociateTag'] = $this->opt[$aid_domain];
            }

            return $param;
        }

        /**
         *--------------------------------------------------------------------
         * リクエスト実行
         *-------------------------------------------------------------------*/
        private function request($domain, $query, $secure, $cache_key) {
            // endpoint決定
            $aid_domain = $this->domain_to_aid_domain($domain);
            if($secure){
                $endpoint = libpw_Amazon_API::$ENDPOINTS[$aid_domain];
            }else{
                $endpoint = libpw_Amazon_API::$ENDPOINT[$aid_domain];
            }

            // 電子署名の作成
            $parseurl  = parse_url($endpoint);
            $signature = base64_encode(hash_hmac('sha256',
                                                 "GET\n{$parseurl['host']}\n{$parseurl['path']}\n{$query}",
                                                 $this->opt[self::PRIVATE_KEY],
                                                 true));

            // リクエストの作成
            $request = $endpoint . '?' . $query . '&Signature=' . str_replace('%7E', '~', rawurlencode($signature));

            // データ取得
            $wait = self::REQUEST_WAIT;
            $xml  = false;

            // クリティカルセクションで挟む
            if($this->critical->start($wait)){
                $curl = curl_init($request);
                curl_setopt($curl, CURLOPT_TIMEOUT, 60);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_AUTOREFERER, true);
                $xml = curl_exec($curl);
                curl_close($curl);
                //$xml = @file_get_contents($request);
                if($xml !== false){
                    $this->cache->set($cache_key, $xml);
                }
                $this->critical->end($wait);
            }

            return $xml;
        }

        /**
         *====================================================================
         * ItemLookup
         *===================================================================*/
        public function itemLookup($domain, $asin, $responce_group = 'Images,ItemAttributes,OfferSummary', $secure = false, $xml_error_handling = true) {
            $domain = str_replace('.', '_', $domain); // . を _ に変換

            if($domain == 'amazon_jp'){
                $domain = 'amazon_co_jp';
            }

            $item_id   = libpw_Amazon_ISBN::to10($asin);
            $cache_key = $domain . $item_id . $responce_group;
            $result    = false;
            $xml       = false;

            if($xml = $this->cache->get($cache_key)){
                // キャッシュがあったのでそれを利用
            }else if(($this->opt[self::ACCESS_KEY] != '') && ($this->opt[self::PRIVATE_KEY])){
                // キャッシュが無かったのでAPIを利用して情報取得
                $param = $this->get_common_parameter($domain);
                $param['Operation']     = 'ItemLookup';
                $param['ItemId']        = $item_id;
                $param['ResponseGroup'] = $responce_group;

                // クエリ作成
                ksort($param);
                $q = '';
                foreach($param as $key => $val){
                    $q .= '&' . str_replace('%7E', '~', rawurlencode($key)) . '=' . str_replace('%7E', '~', rawurlencode($val));
                }
                $q = substr($q, 1); // 先頭の&を取り除く

                // リクエスト発行
                $xml = $this->request($domain, $q, $secure, $cache_key);
            }else{
                $xml = false;
            }

            if($xml != false){
                $xml = simplexml_load_string($xml);
            }
            
            return $xml;
        }
    }
}
