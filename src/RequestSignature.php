<?php

namespace Ratno\Transport;

class RequestSignature
{
    public function urldecode($s)
    {
        if ($s === false) {
            return $s;
        } else {
            return rawurldecode($s);
        }
    }

    public function createNonce($unique = false)
    {
        $key = md5(uniqid(rand(), true));
        if ($unique) {
            list($usec, $sec) = explode(' ', microtime());
            $key .= dechex($usec) . dechex($sec);
        }
        return $key;
    }

    public function createSignatureBase($method, $url, $params)
    {
        $params = (empty($params)) ? '' : $this->normalizeParams($params);
        $sigbase = array($method,
            $this->urlencode($url),
            $this->urlencode($params));

        return implode('&', $sigbase);
    }

    public function normalizeParams($params)
    {
        if (empty($params)) return;

        $normalized = array();

        ksort($params);
        foreach ($params as $name => $value) {
            if (is_array($value) && sizeof($value)) {
                sort($value);

                for ($i = 0; $i < sizeof($value); $i++) {
                    $normalized[] = "$name=" . $this->urlencode($value[$i]);
                }
            } else {
                $normalized[] = "$name=" . $this->urlencode($value);
            }
        }

        return implode("&", $normalized);
    }

    public function urlencode($s)
    {
        if ($s === false) {
            return $s;
        } else {
            return str_replace('%7E', '~', rawurlencode($s));
        }
    }

    public function createSignature($signatureBase, $clientId, $clientSecret)
    {
        $key = $this->urlencode($clientId) . '&' . $this->urlencode($clientSecret);

        if (function_exists('hash_hmac')) {
            $signature = base64_encode(hash_hmac("sha1", $signatureBase, $key, true));
        } else {
            $blocksize = 64;
            $hashfunc = 'sha1';

            if (strlen($key) > $blocksize) {
                $key = pack('H*', $hashfunc($key));
            }

            $key = str_pad($key, $blocksize, chr(0x00));
            $ipad = str_repeat(chr(0x36), $blocksize);
            $opad = str_repeat(chr(0x5c), $blocksize);
            $hmac = pack(
                'H*', $hashfunc(
                    ($key ^ $opad) . pack(
                        'H*', $hashfunc(
                            ($key ^ $ipad) . $signatureBase
                        )
                    )
                )
            );

            $signature = base64_encode($hmac);
        }

        return $this->urlencode($signature);
    }
}