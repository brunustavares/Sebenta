<?php
/**
 * Sebenta
 * Moodle block for grades synchronization with WISEflow (teachers’ function)
 * and integrated grades and submission statements (students’ function).
 * (developed for UAb - Universidade Aberta)
 *
 * @category   php_config
 * @package    auth_lib_mdl
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2022-2025 Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2023031005
 * @date       2022-10-27
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

require_once ($CFG->dirroot . '/config.php');

define('GARBAGE', 'amp%3B');

// variáveis globais
    global $wf_base_url;
    global $bdint_ws_URL;
    global $host;
    global $port;
    global $db;
    global $usr;
    global $pwd;
    global $privateKey;
    global $secretKey;
    global $encryptMethod;

// WISEflow
    // parte do URL comum a todas as APIs
        $wf_base_url  = 'https://<hidden-url>/';

    // cadeias p/ reforço da encriptação
        $privateKey    = '<hidden-private-key>';
        $secretKey     = '<hidden-secret-key>';
        $encryptMethod = "<hidden-encryption-method>";

    /**
    * Encriptação do token de acesso às APIs, para guardar em ficheiro
    *
    * @return string encrypted_string
    */
    function encrypt_token($token_string)
    {
        global $privateKey;
        global $secretKey;
        global $encryptMethod;

        $encrypted_string = "<hidden-encryption-algorithm>";

        return $encrypted_string;

    }

    /**
    * Desencriptação do token de acesso às APIs, após leitura em ficheiro
    *
    * @return string token_string
    */
    function decrypt_token($encrypted_string)
    {
        global $privateKey;
        global $secretKey;
        global $encryptMethod;

        $token_string = "<hidden-decryption-algorithm>";

        return $token_string;

    }

    /**
     * Obtenção do token de acesso às APIs
     *
     * @return array token
     */
    function getwftoken()
    {
        global $wf_base_url;

        $client_id     = '<hidden-client-id>';
        $client_secret = '<hidden-client-secret>';
        $grant_type    = 'client_credentials';

        $token[] = '';

        $url = $wf_base_url . "oauth2/token";

        $auth = ['client_id'=>$client_id,
                 'client_secret'=>$client_secret,
                 'grant_type'=>$grant_type];

        $headers = array(
                         "accept:application/json",
                         "content-type:application/x-www-form-urlencoded",
                        );

        $data = array(
                      CURLOPT_POST => true,
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => '',
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 0,
                      CURLOPT_FOLLOWLOCATION => true,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_SSL_VERIFYHOST => false,
                      CURLOPT_SSL_VERIFYPEER => false,
                      CURLOPT_URL => $url,
                      CURLOPT_HTTPHEADER => $headers,
                      CURLOPT_POSTFIELDS => rawurldecode(str_replace(GARBAGE, '', rawurlencode((string)http_build_query($auth))))
                     );

        $curl = curl_init();

        curl_setopt_array($curl, $data);

        $response = curl_exec($curl);
        // $errNo    = curl_errno($curl);
        // $err      = curl_error($curl);

        curl_close($curl);

        $flow = json_decode($response, true);

        $token = ['chain'=>$flow['access_token'],
                  'expire'=>$flow['expires_in'],
                  'type'=>$flow['token_type']];

        return $token;

    }

// base de dados intermédia - BDInt
    // parâmetros comuns às funções RW
        $bdint_ws_URL = '<hidden-bdint-ws-url>';
        $host         = '<hidden-host>';
        $port         = '<hidden-port>';
        $db           = '<hidden-db>';
        $usr          = '<hidden-usr>';
        $pwd          = '<hidden-pwd>';

    /**
     * Leitura da BDInt
     *
     * @return URL connstr
     */
    function getbdintdata($format=null, $op=null)
    {
        global $bdint_ws_URL;
        global $host;
        global $port;
        global $db;
        global $usr;
        global $pwd;

        //TODO: implementar curl
        $connstr = new moodle_url($bdint_ws_URL,
                                  ['BDhost'=>$host,
                                   'BDhostPrt'=>$port,
                                   'BDInt'=>$db,
                                   'BDusr'=>$usr,
                                   'BDpwd'=>$pwd,
                                   'format'=>$format,
                                   $op=>(isset($op)) ? "yes" : null],
                                 );

        return rawurldecode(str_replace(GARBAGE, '', rawurlencode((string)$connstr)));

    }

    /**
     * Actualização da BDInt
     *
     */
    function setbdintdata($rec2updt, $data)
    {
        global $bdint_ws_URL;
        global $host;
        global $port;
        global $db;
        global $usr;
        global $pwd;

        $db_id = ['BDhost'=>$host,
                  'BDhostPrt'=>$port,
                  'BDInt'=>$db];

        $auth = ['BDusr'=>$usr,
                 'BDpwd'=>$pwd];

        if ($data == 'assess') {
            $key = 'setPart';

        } elseif ($data == 'grades') {
            $key = 'updtLst';
            
        } elseif ($data == 'nees') {
            $key = 'setNEEs';
                        
        }

        $params = ['op'=>'set',
                    $key=>$rec2updt];

        $furl = rawurldecode(str_replace(GARBAGE, '', rawurlencode((string)$bdint_ws_URL . '?' . http_build_query(array_merge($db_id, $auth, $params)))));

        $curl = curl_init();

        $data = array(
                      CURLOPT_URL => $furl,
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_SSL_VERIFYHOST => false,
                      CURLOPT_SSL_VERIFYPEER => false
                     );

        curl_setopt_array($curl, $data);

        $response  = curl_exec($curl);
        // $errNo = curl_errno($curl);
        // $err   = curl_error($curl);

        curl_close($curl);
        unset($response);
        
    }
