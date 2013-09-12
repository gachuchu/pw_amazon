<?php /*@charset "utf-8"*/
/*********************************************************************
 Plugin Name:   PW_Amazon
 Plugin URI:    http://syncroot.com/
 Description:   アマゾン商品リンクをPAAPIで取得したデータでそれなりに置き換えるプラグインです。
 Author:        gachuchu
 Version:       1.0.0
 Author URI:    http://syncroot.com/
 *********************************************************************/

/*********************************************************************
 Copyright 2010 gachuchu  (email : syncroot.com@gmail.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *********************************************************************/

require_once(WP_PLUGIN_DIR . "/libpw/libpw.php");
require(dirname(__FILE__) . '/libpw_amazon_api.php');

if(!class_exists('PW_Amazon')){
    /**
     *********************************************************************
     * 本体
     *********************************************************************/
    class PW_Amazon extends libpw_Plugin_Substance {
        //---------------------------------------------------------------------
        const UNIQUE_KEY  = 'PW_Amazon';
        const CLASS_NAME  = 'PW_Amazon';
        const ENCRYPT_KEY = '';

        //---------------------------------------------------------------------
        const RES_GROUP = 'responce_group';

        //---------------------------------------------------------------------
        private $opt;
        private $api;

        /**
         *====================================================================
         * 初期化
         *===================================================================*/
        public function init() {
            // オプション設定
            $this->opt = new libpw_Plugin_DataStore($this->unique . '_OPT',
                                                    array(
                                                        libpw_Amazon_API::TRAKING_ID_JP     => '',
                                                        libpw_Amazon_API::TRAKING_ID_US     => '',
                                                        libpw_Amazon_API::TRAKING_ID_UK     => '',
                                                        libpw_Amazon_API::TRAKING_ID_DE     => '',
                                                        libpw_Amazon_API::TRAKING_ID_FR     => '',
                                                        libpw_Amazon_API::TRAKING_ID_CA     => '',
                                                        libpw_Amazon_API::ACCESS_KEY        => '',
                                                        libpw_Amazon_API::PRIVATE_KEY       => '',
                                                        self::RES_GROUP                    => 'Images,ItemAttributes,OfferSummary',
                                                        ),
                                                    self::ENCRYPT_KEY
                                                    );

            // api作成
            $this->opt->load();
            $api_opt = $this->opt->getAll();
            $api_opt[libpw_Amazon_API::LOCKFILE]  = dirname(__FILE__) . '/lock/lockfile';
            $api_opt[libpw_Amazon_API::CACHE_DIR] = dirname(__FILE__) . '/cache/';
            $this->api = new libpw_Amazon_API($api_opt);
            $this->opt->clear();

            // 管理メニュー
            $this->addMenu($this->unique . 'の設定ページ',
                           $this->unique);

            // 変換処理
            add_filter('the_content', array(&$this, 'execute'));
        }

        /**
         *====================================================================
         * deactivate
         *===================================================================*/
        public function deactivate() {
            $this->opt->delete();
        }

        static public function uninstall() {
            $ref = self::getInstance(self::CLASS_NAME);
            $ref->opt->delete();
        }

        /**
         *====================================================================
         * amazonリンクの変換
         *===================================================================*/
        public function execute($content) {
            // 可能性があるかどうかをチェック
            if((strpos($content, 'amazon') === false) && (strpos($content, 'javari'))){
                return $content;
            }

            $this->opt->load();

            // iframeじゃないのも取れるけど正直微妙
            $regdomain = 'https?:\/\/(?:www\.|rcm\.|rcm-jp\.|rcm-fe\.)?(?P<domain>(?:amazon\.(?:ca|de|fr|(?:co\.)?jp|co\.uk|com)|javari\.jp|amazon-adsystem\.com))\/';
            $regasin   = '(?P<asin>[A-Za-z0-9]{10})[^"]*"[^>]*?>).*?<\/a>/s';
            $regexps   = array();
            $regexps[] = '/(?P<atag><a .*?href="' . $regdomain . '(?P<urlname>\S+)\/dp\/' . $regasin;
            $regexps[] = '/(?P<atag><a .*?href="' . $regdomain . '(?P<urlname>\S+)\/gp\/product\/' . $regasin;
            $regexps[] = '/(?P<atag><a .*?href="' . $regdomain . 'gp\/product\/' . $regasin;
            $regexps[] = '/(?P<atag><iframe [^>]*?src="' . $regdomain . '.*?asins=(?P<asin>[A-Za-z0-9]{10})[^>]*?>).*?<\/iframe>/s';
            $chkignore = 'data-pw-amazon-ignore="true"';

            foreach($regexps as $regexp){
                if($num = preg_match_all($regexp, $content, $res)){
                    for($i = 0; $i < $num; ++$i){
                        if(strpos($res['atag'][$i], $chkignore)){
                            continue;
                        }
                        $tmp = array();
                        $tmp['domain'] = $res['domain'][$i];
                        if($tmp['domain'] == 'amazon.jp'){
                            $tmp['domain'] = 'amazon.co.jp';
                        }
                        if($tmp['domain'] == 'amazon-adsystem.com'){
                            $tmp['domain'] = 'amazon.co.jp';
                        }
                        $tmp['asin'] = $res['asin'][$i];

                        $xml = $this->api->itemLookup($tmp['domain'],
                                                      $tmp['asin'],
                                                      $this->opt->get(self::RES_GROUP)
                                                      );
                        $rep_str = $this->get_replace_str($xml, $tmp);
                        if($rep_str){
                            $content = str_replace($res[0][$i], $rep_str, $content);
                        }
                    }
                }
            }

            $this->opt->clear();

            return $content;
        }

        /**
         *====================================================================
         * render
         *===================================================================*/
        public function render() {
            $this->opt->load();

            $this->renderStart($this->unique . 'の設定項目');
            if($this->request->isUpdate()){
                $this->renderUpdate('<p>設定を更新しました </p>');
                $this->opt->update($this->request->getAll());
                $this->opt->save();
            }

            $this->renderTableStart();

            //---------------------------------------------------------------------
            $this->renderTableLine();

            // トラッキングID
            $traking_id_list = array(
                libpw_Amazon_API::TRAKING_ID_JP    => 'トラッキングID(日本)',
                libpw_Amazon_API::TRAKING_ID_US    => 'トラッキングID(アメリカ)',
                libpw_Amazon_API::TRAKING_ID_UK    => 'トラッキングID(イギリス)',
                libpw_Amazon_API::TRAKING_ID_DE    => 'トラッキングID(ドイツ)',
                libpw_Amazon_API::TRAKING_ID_FR    => 'トラッキングID(フランス)',
                libpw_Amazon_API::TRAKING_ID_CA    => 'トラッキングID(カナダ)',
                );

            foreach($traking_id_list as $traking_id => $label) {
                $val = $this->opt->get($traking_id);
                $this->renderTableNode(
                    $label,
                    '<input type="text" size="60" name="' . $traking_id . '" value="' . $val . '" />'
                    );
            }

            //---------------------------------------------------------------------
            $this->renderTableLine();

            // アクセスキー
            $val = $this->opt->get(libpw_Amazon_API::ACCESS_KEY);
            $this->renderTableNode(
                'アクセスキーID',
                '<input type="text" size="60" name="' . libpw_Amazon_API::ACCESS_KEY . '" value="' . $val . '" />'
                );

            // シークレットアクセスキー
            $val = $this->opt->get(libpw_Amazon_API::PRIVATE_KEY);
            $this->renderTableNode(
                'シークレットアクセスキー',
                '<input type="text" size="60" name="' . libpw_Amazon_API::PRIVATE_KEY . '" value="' . $val . '" />'
                );

            //---------------------------------------------------------------------
            $this->renderTableLine();

            // ResponceGroup
            $val = $this->opt->get(self::RES_GROUP);
            $this->renderTableNode(
                'ResponceGroup',
                '<input type="text" size="60" name="' . self::RES_GROUP . '" value="' . $val . '" />'
                );

            //---------------------------------------------------------------------
            $this->renderTableEnd();

            $this->renderSubmit('変更を保存');
            $this->renderEnd();

            $this->opt->clear();
        }

        /**
         *--------------------------------------------------------------------
         * 画像取得
         *-------------------------------------------------------------------*/
        private function get_image_info(&$imgset) {
            $size = array('LargeImage',
                          'MediumImage',
                          'TinyImage',
                          'SmallImage',
                          'SwatchImage',
                          );
            if($imgset){
                foreach($size as $s){
                    if($imgset->$s){
                        return $imgset->$s;
                    }
                }
            }
            return null;
        }

        /**
         *--------------------------------------------------------------------
         * 結果出力
         *-------------------------------------------------------------------*/
        private function get_replace_str(&$xml, &$reg) {
            // 必要な情報の収集
            $url      = &$xml->Items->Item->DetailPageURL;
            $imgset   = &$xml->Items->Item->ImageSets->ImageSet;
            $item_atr = &$xml->Items->Item->ItemAttributes;

            if(!$xml || !$url || !$imgset || !$item_atr){
                return false;
            }

            // 追加情報
            for($i = 0; $tmp = $xml->OperationRequest->Arguments->Argument[$i]; ++$i){
                if($tmp['Name'] == 'AssociateTag'){
                    $atag = $tmp['Value'];
                    break;
                }
            }
            $offer_summary = $xml->Items->Item->OfferSummary;
            $img_info      = $this->get_image_info($imgset);
            $name          = $item_atr->Title;

            // dt部
            if(!$img_info){
                $dt = '<dt class="noimage">no image</dt>';
            }else{
                $src = 'src="' . $img_info->URL . '"';
                $hw  = 'height="' . $img_info->Height . '" width="' . $img_info->Width . '"';
                $alt = 'alt="' . htmlspecialchars($name) . '"';
                $dt  = "<dt><a href=\"{$url}\"><img {$src} {$alt} {$hw} /></a></dt>";
            }

            // dd部
            $dd = '<dd><ul>';
            $dd .= "<li><a href=\"{$url}\">{$name}</a></li>";
            switch($item_atr->ProductGroup){
                //---------------------------------------------------------------------
                // 本
            case 'Book':
                if($item_atr->Author){
                    $auth = (is_array($item_atr->Author) ? implode('&nbsp;', $item_atr->Author):$item_atr->Author);
                    $dd .= "<li>著者/訳者:{$auth}</li>";
                }
                if($item_atr->Binding){
                    $binding = $item_atr->Binding;
                    if($item_atr->NumberOfPages){
                        $binding .= '(' . $item_atr->NumberOfPages . 'ページ)';
                    }
                    $dd .= "<li>{$binding}</li>";
                }
                if($item_atr->Manufacturer){
                    $publisher = $item_atr->Manufacturer;
                    if($item_atr->PublicationDate){
                        $publisher .= ' (' . $item_atr->PublicationDate . ')';
                    }
                    $dd .= "<li>出版:{$publisher}</li>";
                }
                break;

            case 'DVD':
                if($item_atr->Director){
                    $director = (is_array($item_atr->Director) ? implode('&nbsp;', $item_atr->Director):$item_atr->Director);
                    $dd .= "<li>監督:{$director}</li>";
                }
                if($item_atr->Actor){
                    $actor = (is_array($item_atr->Actor) ? implode('&nbsp;', $item_atr->Actor):$item_atr->Actor);
                    $dd .= "<li>出演:{$actor}</li>";
                }
                if($item_atr->NumberOfDiscs || $item_atr->RunningTime){
                    $dd .= '<li>';
                    if($item_atr->NumberOfDiscs){
                        if($item_atr->Binding){
                            $dd .= $item_atr->Binding;
                        }
                        $dd .= $item_atr->NumberOfDiscs . '枚組';
                    }
                    if($item_atr->RunningTime){
                        $dd .= ' ' . $item_atr->RunningTime . '分 ';
                    }
                    $dd .= '</li>';
                }
                if($item_atr->Manufacturer){
                    $publisher = $item_atr->Manufacturer;
                    if($item_atr->ReleaseDate){
                        $publisher .= ' (' . $item_atr->ReleaseDate . ')';
                    }
                    $dd .= "<li>販売:{$publisher}</li>";
                }
                break;

            case 'Music':
                if($item_atr->Artist){
                    $artist = (is_array($item_atr->Artist) ? implode('&nbsp;', $item_atr->Artist):$item_atr->Artist);
                    $dd .= "<li>アーティスト:{$artist}</li>";
                }
                if($item_atr->Manufacturer){
                    $publisher = $item_atr->Manufacturer;
                    if($item_atr->ReleaseDate){
                        $publisher .= ' (' . $item_atr->ReleaseDate . ')';
                    }
                    $dd .= "<li>レーベル:{$publisher}</li>";
                }
                break;

            default:
                break;
            }

            // 価格表示
            $dd .= '<li class="price">価格:';
            $price = $lowprice = $item_atr->ListPrice;
            if(!empty($price)){
                if($offer_summary){
                    if(($lowprice1 = $offer_summary->LowestNewPrice) && ((int)($lowprice1->Amount) < (int)($lowprice->Amount))){
                        $lowprice = $lowprice1;
                    }
                    if(($lowprice2 = $offer_summary->LowestUsedPrice) && ((int)($lowprice2->Amount) < (int)($lowprice->Amount))){
                        $lowprice = $lowprice2;
                        $lowprice->FormattedPrice .= '(used)';
                    }
                }
            }else if($offer_summary){
                if(($lowprice1 = $offer_summary->LowestNewPrice)){
                    $price = $lowprice = $lowprice1;
                }
                if(($lowprice2 = $offer_summary->LowestUsedPrice) && ((int)($lowprice2->Amount) < (int)($lowprice->Amount))){
                    $price = $lowprice = $lowprice2;
                    $lowprice->FormattedPrice .= '(used)';
                }
            }
            $set_price = '不明';
            if($price){
                if((int)($lowprice->Amount) < (int)($price->Amount)){
                    $set_price = "{$price->FormattedPrice} ～ {$lowprice->FormattedPrice}";
                    $dd .= "{$price->FormattedPrice} ～ <span>{$lowprice->FormattedPrice}</span>";
                }else{
                    $set_price = "{$lowprice->FormattedPrice}";
                    $dd .= "<span>{$lowprice->FormattedPrice}</span>";
                }
            }else{
                $dd .= "<span>不明</span>(売り切れかも)";
            }
            $dd .= '</li>';
            $dd .= '</ul>';

            // カートボタン
            $cartact  = 'http://www.' . $reg['domain'] . '/gp/aws/cart/add.html';
            $cartadd  = '<form method="POST" target="_blank" action="' . $cartact . '">';
            $cartadd .= '<input type="hidden" name="ASIN.1" value="' . $reg['asin'] . '">';
            $cartadd .= '<input type="hidden" name="Quantity.1" value="1">';
            if($atag !== '' && !is_user_logged_in()){
                $cartadd .= '<input type="hidden" name="AssociateTag" value="' . $atag . '">';
            }
            $cartadd .= '<input type="hidden" name="AWSAccessKeyId" value="' . $this->opt->get(libpw_Amazon_API::ACCESS_KEY) . '">';
            $cartadd .= '<input type="submit" name="add" alt="カートにいれる" class="submit" value="' . $reg['domain'] . '"></form>';
            $dd .= $cartadd;
            
            $dd .= '</dd>';

            // 返却情報を作成
            return "<dl class=\"amazon ad\" data-ad-kind=\"amazon\" data-ad-name=\"{$set_price}円:" . htmlspecialchars($name) . "\">{$dt}{$dd}</dl>";
        }
    }


    /**
     *********************************************************************
     * 初期化
     *********************************************************************/
    PW_Amazon::create(PW_Amazon::UNIQUE_KEY,
                      PW_Amazon::CLASS_NAME,
                      __FILE__);

}
