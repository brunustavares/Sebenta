<?php
/**
 * Sebenta
 * Moodle block for grades synchronization with WISEflow (teachers’ function)
 * and integrated grades and submission statements (students’ function).
 * (developed for UAb - Universidade Aberta)
 *
 * @category   Moodle_Block
 * @package    block_sebenta
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2023-2025 Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2025021305
 * @date       2023-03-21
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

 require_once($CFG->dirroot . '/admin/auth_lib_mdl.php');

/**
 * Validação do token de acesso às APIs
 *
 * @return string auth_chain
 */
function checkwftoken()
{
    global $CFG;

    $start_time = date(time());
    
    $token_file = $CFG->dataroot . "/temp/auth.tkn";
    $newtoken = false;

    if (file_exists($token_file)) { // obtenção de token em ficheiro

        $keys = array('chain', 'expire', 'type');
        $values = explode(";", decrypt_token(file_get_contents($token_file, false)));

        $token = array_combine($keys, $values);

        $tkn_expire = filemtime($token_file) + (int)($token['expire']);

        if ($start_time >= ($tkn_expire - 180)) { $newtoken = true; } // token a expirar em 3min
    
    } else { $newtoken = true; }

    if ($newtoken) { // obtenção de token válido e gravação em ficheiro
        $token = getwftoken();

        file_put_contents($token_file, encrypt_token($token['chain'] . ";" . $token['expire'] . ";" . $token['type']));

    }
    
    $auth_chain = $token['type'] . " " . $token['chain'];
        
    return $auth_chain;

}

/**
 * Configura valores padrão p/ os requests curl
 *
 * @return array curlopt_base
 */
function set_curl_params()
{
    $auth_chain = checkwftoken();

    $headers = array(
                     "accept:application/json",
                     "content-type:application/json",
                     "authorization:" . $auth_chain,
                    );

    $curlopt_base = array(
                          CURLOPT_RETURNTRANSFER => true,
                          CURLOPT_ENCODING => '',
                          CURLOPT_MAXREDIRS => 10,
                          CURLOPT_TIMEOUT => 0,
                          CURLOPT_FOLLOWLOCATION => true,
                          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                          CURLOPT_HTTPHEADER => $headers,
                          CURLOPT_SSL_VERIFYHOST => false,
                          CURLOPT_SSL_VERIFYPEER => false
                         );

    return $curlopt_base;

}

//  TODO: carregamento progressivo
if (isset($_GET['flows_array']))
{
    $flows = $_GET['flows_array'];

    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $limit = 50;

    $data = array_slice($flows, $start, $limit);










    // Return JSON data
    header('Content-Type: application/json');
    echo json_encode($data);

}
