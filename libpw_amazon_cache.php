<?php /*@charset "utf-8"*/
/**
 *********************************************************************
 * キャッシュオブジェクト v1.0
 * @file   libpw_cache.php
 * @date   2013-03-09 01:46:22 (Saturday)
 *********************************************************************/

/**
 *====================================================================
 * 本体
 *===================================================================*/
if(!class_exists('libpw_Amazon_Cache')){
    class libpw_Amazon_Cache {
        private $cache;
        
        /**
         *====================================================================
         * construct
         *===================================================================*/
        public function __construct($lifetime = 3600, $dir = '/tmp/', $factor = 100) {
            require_once('Cache/Lite.php');
            $option = array('lifeTime'                  => $lifetime,
                            'cacheDir'                  => $dir,
                            'automaticCleaningFactor'   => $factor,
                            );
            $this->cache = new Cache_Lite($option);
        }

        /**
         *====================================================================
         * set
         *===================================================================*/
        public function get($key) {
            $data = $this->cache->get($key);
            return $data;
        }

        /**
         *====================================================================
         * get
         *===================================================================*/
        public function set($key, $data) {
            $this->cache->save($data, $key);
        }

        /**
         *====================================================================
         * clear
         *===================================================================*/
        public function clear() {
            $this->cache->clean();
        }
    }
}
