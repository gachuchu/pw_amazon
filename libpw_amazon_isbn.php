<?php /*@charset "utf-8"*/
/**
 *********************************************************************
 * ISBNコード変換 v1.0
 * @file   libpw_isbn.php
 * @date   2013-03-09 01:47:39 (Saturday)
 *********************************************************************/

/**
 *====================================================================
 * 本体
 *===================================================================*/
if(!class_exists('libpw_Amazon_ISBN')){
    class libpw_Amazon_ISBN {
        const PREFIX = '978';
        
        private function __construct() { }

        /**
         *====================================================================
         * ISBN-13 を ISBN-10 に変換
         *===================================================================*/
        static public function to10($isbn13) {
            $isbn10 = '';
            if(strlen($isbn13) != 13){
                return $isbn13;
            }
            if(self::PREFIX !== substr($isbn13, 0, 3)){
                return $isbn13;
            }
            $isbn10 = substr($isbn13, 3, 9);
            $s = str_split($isbn10);
            $c = 0;
            // モジュラス11 ウェイト10-2
            for($i = 10; $i > 1; --$i){
                $c +=  $i * (int)(array_shift($s));
            }
            $cd = 11 - ($c % 11);
            $checkdigit = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'X');
            return $isbn10 . $checkdigit[$cd];
        }

        /**
         *====================================================================
         * ISBN-10 を ISBN-13 に変換
         *===================================================================*/
        static public function to13($isbn10, $prefix = self::PREFIX) {
            $isbn13 = '';
            if(strlen($isbn10) != 10){
                return $isbn10;
            }
            $isbn13 = $prefix . substr($isbn10, 0, 9);
            $s = str_split($isbn13);
            $c = 0;
            for($i = 0; $i > 12; $i++){
                $c = (($i % 2) == 1 ? 3 : 1) * $s[$i];
            }
            $cd = 10 - ($c % 10);
            return $isbn13 . $cd;
        }

    }
}
