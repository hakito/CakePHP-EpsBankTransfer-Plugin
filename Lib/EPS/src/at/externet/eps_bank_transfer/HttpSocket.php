<?php

namespace at\externet\eps_bank_transfer;

class HttpSocket
{
    /**
     * Wrapper for http_get
     * @param string $url URL
     * @param array $options request options
     * @param array $info Will be filled with request/response information
     * @return string 
     */
    public function get($url, $options = null, &$info = null)
    {        
        return http_get($url, $options, $info);        
    }
    
    /**
     * Wrapper for http_post_data
     * @param string $url URL
     * @param string $data String containing the pre-encoded post data
     * @param array $options request options
     * @param array $info Request/response information
     * @return string
     */
    public function post_data($url, $data, $options = null, &$info = null)
    {
        return http_post_data($url, $data, $options, $info);
    }
            
}