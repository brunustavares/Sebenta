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

if(isset($_POST['action']) && isset($_POST['auth_chain']) && isset($_POST['url'])) {
    if($_POST['action'] == "endflowmarking" && isset($_POST['flowid'])) {
        endflowmarking($_POST['flowid']);
    }
} else { die(); }

/**
 * Configura valores padrão p/ os requests curl
 *
 * @return array
 */
function set_curl_params()
{
    $headers = array(
                     "accept:application/json",
                     "content-type:application/json",
                     "authorization:" . base64_decode($_POST['auth_chain']),
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

/**
 * Alteração da data de fim da avaliação do flow
 *
 */
function endflowmarking($flowid)
{
    $url = base64_decode($_POST['url']) . "flows/" . $flowid . "/dates";

    $end_date = date(time());

    $data = <<<DATA
                   {
                    "participation": {},
                    "marking": {
                                "end": $end_date
                    }
                   }
            DATA;

    $curlopt = array_replace(
                             set_curl_params(),
                             array(
                                   CURLOPT_URL => $url,
                                   CURLOPT_CUSTOMREQUEST => 'PATCH',
                                   CURLOPT_POSTFIELDS => $data,
                                  )
                            );

    $curl = curl_init();

    curl_setopt_array($curl, $curlopt);

    $response = curl_exec($curl);
    // $errNo = curl_errno($curl);
    // $err = curl_error($curl);

    curl_close($curl);
    unset($response);

}
