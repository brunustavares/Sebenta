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
 * @copyright  Copyright (C) 2023-present Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2026021202
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

if (!defined('MOODLE_INTERNAL')) {
    define('AJAX_SCRIPT', true);
    require_once(__DIR__ . '/../../config.php');
    require_login();
}

require_once($CFG->dirroot . '/admin/auth_lib_mdl.php');

/**
 * Validação do token de acesso às APIs
 *
 * @return string auth_chain
 */
function checkwftoken()
{
    global $CFG;

    $now = time();
    $tokenfile = $CFG->dataroot . '/temp/auth.tkn';
    $newtoken = false;

    if (file_exists($tokenfile)) { // obtenção de token em ficheiro
        $keys = array('chain', 'expire', 'type');
        $values = explode(';', decrypt_token(file_get_contents($tokenfile, false)));
        $token = array_combine($keys, $values);

        $expire = filemtime($tokenfile) + (int)$token['expire'];

        if ($now >= ($expire - 180)) { $newtoken = true; } // token a expirar em 3min

    } else { $newtoken = true; }

    if ($newtoken) { // obtenção de token válido e gravação em ficheiro
        $token = getwftoken();
        file_put_contents($tokenfile, encrypt_token($token['chain'] . ';' . $token['expire'] . ';' . $token['type']));

    }

    $authchain = $token['type'] . " " . $token['chain'];
        
    return $authchain;
}

/**
 * Configura valores padrão p/ os requests curl
 *
 * @return array curlopt_base
 */
function set_curl_params()
{
    $authchain = checkwftoken();

    $headers = array(
                     "accept:application/json",
                     "content-type:application/json",
                     "authorization:" . $authchain,
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
 * Normalização de timestamp para segundos, se necessário
 * @param mixed $value
 * @return int
 */
function sebenta_normalize_epoch($value)
{
    $epoch = (int)$value;

    if ($epoch > 20000000000) {
        $epoch = (int)floor($epoch / 1000);

    }

    return $epoch;
}

/**
 * Executa request GET e retorna resposta em array associativo
 * @param string $url
 * @param array $curloptbase
 * @return array|null
 */
function sebenta_wf_get_json($url, array $curloptbase)
{
    $attempt = 0;

    while ($attempt < 2) {
        $curlopt = array_replace(
            $curloptbase,
            array(
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => 'GET',
            )
        );

        $curl = curl_init();
        curl_setopt_array($curl, $curlopt);

        $response = curl_exec($curl);
        $httpcode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($httpcode === 200 && $response !== false) {
            $decoded = json_decode($response, true);
            return is_array($decoded) ? $decoded : null;

        }

        $attempt++;
    }

    return null;

}

/**
 * Gera o HTML da linha do fluxo, correlacionado as submissões e as notas lançadas
 * @param array $flow
 * @param int $assess
 * @return string
 */
function sebenta_render_teacher_flow_row(array $flow, $assess)
{
    global $wf_base_url;

    static $authchainencoded = null;
    static $wfurlencoded = null;

    if ($authchainencoded === null) {
        $authchainencoded = base64_encode(checkwftoken());
    }

    if ($wfurlencoded === null) {
        $wfurlencoded = base64_encode($wf_base_url);
    }

    $flowid = (int)$flow['flowid'];
    $subs = (int)$flow['subs'];

    $percent = $subs > 0 ? round(($assess / $subs) * 100, 2) : 0;
    $percenttxt = $percent . '%';
    $assessinfo = '(' . $assess . ' notas lançadas, em ' . $subs . ' submissões)';

    $subtitle = htmlspecialchars((string)$flow['subtitle'], ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars((string)$flow['title'], ENT_QUOTES, 'UTF-8');
    $flowinfojs = htmlspecialchars(
        json_encode((string)$flow['subtitle'] . ' | ' . (string)$flow['title']),
        ENT_QUOTES,
        'UTF-8'
    );
    $authchainjs = htmlspecialchars(json_encode($authchainencoded), ENT_QUOTES, 'UTF-8');
    $wfurljs = htmlspecialchars(json_encode($wfurlencoded), ENT_QUOTES, 'UTF-8');

    $meterclass = 'meter';
    $button = '<a class="disabled-buttonClass" title="finalização possível após lançamento integral e consonante com o número de submissões">finalizar</a>';
    $barwidth = 'calc(100% - 120px)';

    if ($assess === $subs) {
        $button = '<button type="button" class="btn btn-info btn-lg buttonClass" data-toggle="modal" data-target="#myModal" title="finalizar o lançamento das notas" onclick="setFlowInfo(' . $flowid . ', ' . $flowinfojs . ', ' . $authchainjs . ', ' . $wfurljs . ')">finalizar</button>';

    } else if ($assess === 0 || $assess > $subs) {
        $meterclass .= ' red';

    } else {
        $meterclass .= ' orange';
        $barwidth = 'calc(' . $percenttxt . ' - 120px)';

    }

    $row = '<div class="sebenta-flow-row">
                <div class="' . $meterclass . '" style="display:inline;float:left;width:calc(100% - 125px);">
                    <label style="display:inline;float:left;width:120px;margin-top:-2px;color:white;" title="' . $title . '">
                        <a href="https://europe.wiseflow.net/manager/display.php?id=' . $flowid . '" target="_blank" style="color:white;">' . $subtitle . '</a>
                    </label>
                    <span style="width:' . $barwidth . ';" title="' . htmlspecialchars($assessinfo, ENT_QUOTES, 'UTF-8') . '">' . $percenttxt . '</span>
                </div>';
    $row .= $button;
    $row .= '</div>';

    return $row;

}

/**
 * Inicializa a fonte de dados dos fluxos
 * @param string $flwdoc
 * @return array [scanKey, scan]
 */
function sebenta_bootstrap_flow_source($flwdoc)
{
    global $USER;

    $scanKey = 'sebenta_teacher_scan_' . md5($USER->username . '|' . $flwdoc);
    $ttl = 120;

    if (!empty($_SESSION[$scanKey]) && !empty($_SESSION[$scanKey]['expires']) && $_SESSION[$scanKey]['expires'] > time()) {
        return array($scanKey, $_SESSION[$scanKey]);

    }

    $source = array();
    $totalrecords = 0;

    // acesso à BDInt
    $bdintrecs = @simplexml_load_file((string)getbdintdata('xml') . '&flwDoc=' . rawurlencode($flwdoc));

    if ($bdintrecs) {
        $totalrecords = count($bdintrecs);

        foreach ($bdintrecs as $flow) {
            $flowid = (int)$flow->flw_ID;

            if ($flowid <= 0) { continue; }

            $source[] = array(
                'flowid' => $flowid,
                'title' => (string)$flow->flw_title,
                'subtitle' => (string)$flow->flw_subtitle,
                'subs' => (int)$flow->T_subs,
            );

        }

    }

    $scan = array(
        'expires' => time() + $ttl,
        'cursor' => 0,
        'completed' => false,
        'source' => $source,
        'visible' => array(),
        'totalrecords' => $totalrecords,
    );

    $_SESSION[$scanKey] = $scan;

    return array($scanKey, $scan);

}

/**
 * Expande a lista de fluxos visíveis, até atingir o número alvo ou o máximo
 * @param array $scan
 * @param int $targetCount
 * @return void
 */
function sebenta_expand_visible_flows(&$scan, $targetCount)
{
    global $wf_base_url;

    if ($scan['completed']) { return; }

    $now = time();
    $curloptbase = set_curl_params();

    while (count($scan['visible']) < $targetCount) {
        if ($scan['cursor'] >= count($scan['source'])) {
            $scan['completed'] = true;
            break;

        }

        $flow = $scan['source'][$scan['cursor']];
        $scan['cursor']++;

        if ((int)$flow['subs'] <= 0) { continue; }

        $dates = sebenta_wf_get_json($wf_base_url . 'flows/' . (int)$flow['flowid'] . '/dates', $curloptbase);

        if (!$dates || empty($dates['data']['marking']['start']) || empty($dates['data']['marking']['end'])) {
            continue;
        }

        $start = sebenta_normalize_epoch($dates['data']['marking']['start']);
        $end = sebenta_normalize_epoch($dates['data']['marking']['end']);

        if (!($start <= $now && $now < $end)) { continue; }

        $assessments = sebenta_wf_get_json($wf_base_url . 'flow/' . (int)$flow['flowid'] . '/assessments', $curloptbase);
        $assess = is_array($assessments) ? count($assessments) : 0;

        $scan['visible'][] = array(
            'flowid' => (int)$flow['flowid'],
            'html' => sebenta_render_teacher_flow_row($flow, $assess),
        );

    }

}

/**
 * Request AJAX para obtenção dos fluxos
 *
 * @return void
 */
function sebenta_handle_ajax_flows_request()
{
    $action = isset($_GET['action']) ? (string)$_GET['action'] : '';

    if ($action !== 'get_flows') { return; }

    require_sesskey();

    $flwdoc = isset($_GET['flwDoc']) ? trim((string)$_GET['flwDoc']) : '';
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 10;

    list($scanKey, $scan) = sebenta_bootstrap_flow_source($flwdoc);

    $targetCount = $offset + $limit + 1;
    sebenta_expand_visible_flows($scan, $targetCount);

    $_SESSION[$scanKey] = $scan;

    $visible = $scan['visible'];
    $batch = array_slice($visible, $offset, $limit);

    $hasMore = false;

    if (count($visible) > ($offset + count($batch))
        || (!$scan['completed'])) { $hasMore = true; }

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(array(
        'items' => $batch,
        'offset' => $offset,
        'count' => count($batch),
        'nextOffset' => $offset + count($batch),
        'totalVisible' => count($visible),
        'totalVisibleKnown' => (bool)$scan['completed'],
        'totalRecords' => (int)$scan['totalrecords'],
        'hasMore' => $hasMore,
        'generatedAt' => time(),
    ));

    exit;
}

sebenta_handle_ajax_flows_request();
