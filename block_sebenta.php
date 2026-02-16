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

class block_sebenta extends block_base
{

    public $blockname = null;
    protected $contentgenerated = false;
    protected $docked = null;

    /**
     * Initializes class member variables.
     */
    public function init()
    {
        // Needed by Moodle to differentiate between blocks.
        $this->blockname = get_class($this);
        // $this->title = get_string('pluginname', 'block_sebenta');
        $this->title = '';
        $this->content_type = BLOCK_TYPE_TEXT;

    }

    /**
     * Allows configuration of the block.
     *
     * @return bool True if the configuration is allowed.
     */
    function instance_allow_config()
    {
        return true;
        
    }

    /**
     * Enables global configuration of the block in settings.php.
     *
     * @return bool True if the global configuration is enabled.
     */
    public function has_config()
    {
        return true;

    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats()
    {
        return array('all' => true);

    }

    /**
     * Allows multiple instances.
     *
     * @return bool True if multiple instances are allowed.
     */
    function instance_allow_multiple()
    {
        return false;
        
    }

    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content()
    {
        if ($this->content !== NULL) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        global $CFG, $USER;

        require_once('fetch_flows.php');
        require_once($CFG->dirroot . '/blocks/lanca_pauta/block_lanca_pauta.php');

        date_default_timezone_set('Europe/Lisbon');

        /**
         * Verificação do estatuto de Estudante
         *
         * @return array $result
         */
        function isStudent($usrNm)
        {
            global $DB;

            $result = $DB->get_records_sql(
                                           "SELECT view_ID() AS id,
                                                   usr.username AS usrNm,
                                                   usr.id AS usrID,
                                                   crs.idnumber AS crsIDn,
                                                   crs.id AS crsID,
                                                   crs.fullname AS crsName,
                                                   crs.visible AS crsVis,
                                                   rl.shortname AS usrRl
                                            FROM mdl_course crs
                                                INNER JOIN mdl_context ctx ON (ctx.instanceid = crs.id AND ctx.contextlevel = 50)
                                                INNER JOIN mdl_role_assignments rla ON rla.contextid = ctx.id
                                                INNER JOIN mdl_role rl ON rl.id = rla.roleid
                                                INNER JOIN mdl_user usr ON usr.id = rla.userid
                                            WHERE (crs.idnumber LIKE CONCAT('_____\___\___')
                                                    AND RIGHT(crs.idnumber, 2) REGEXP ('^[0-9]+$')
                                                    AND crs.idnumber NOT LIKE '%_00')
                                                AND crs.visible = 0
                                                AND rl.shortname IN ('student' , 'xp', 'ampv', 'ampv_oa')
                                                AND usr.username = '" . $usrNm . "'
                                            ORDER BY SUBSTR(crs.idnumber, 7, 2) DESC, SUBSTR(crs.idnumber, 1, 5) ASC;"
                                          );

            if ($result) { return $result; }

        }

        /**
         * Obtenção das submissões do Estudante
         *
         * @return array $result
         */
        function getAssignments($usrNm, $crsLst)
        {
            global $DB;

            // $result = $DB->get_records_sql(
            //                                "SELECT view_ID() AS id,
            //                                        usr.username AS usrNm,
            //                                        usr.id AS usrID,
            //                                        crs.idnumber AS crsIDn,
            //                                        crs.id AS crsID,
            //                                        assign.idnumber AS assign,
            //                                        IF(grd_grd.finalgrade >= 0, grd_grd.finalgrade, NULL) AS grade
            //                                 FROM moodle.mdl_grade_grades AS grd_grd
            //                                     INNER JOIN moodle.mdl_grade_items grd_it ON grd_it.id = grd_grd.itemid
            //                                     INNER JOIN moodle.mdl_user AS usr ON usr.id = grd_grd.userid
            //                                     INNER JOIN (
            //                                                 SELECT *
            //                                                 FROM moodle.mdl_course_modules crs_mod
            //                                                 WHERE crs_mod.idnumber LIKE '%folio%'
            //                                                     OR crs_mod.idnumber LIKE 'exame%'
            //                                                     OR crs_mod.idnumber LIKE 'teste%'
            //                                                ) AS assign ON assign.instance = grd_it.iteminstance
            //                                     INNER JOIN moodle.mdl_course crs ON (crs.id = assign.course AND crs.id = grd_it.courseid)
            //                                 WHERE usr.username = '" . $usrNm . "'
            //                                 ORDER BY SUBSTR(crs.idnumber, 7, 2) DESC , SUBSTR(crs.idnumber, 1, 5) ASC , assign.idnumber ASC;"
            //                               );

            $result = $DB->get_records_sql(
                                           "SELECT * FROM moodle.mv_Sebenta_Assigns
                                            WHERE usrNm = '" . $usrNm . "'
                                                AND crsIDn IN (" . $crsLst . ");"
                                          );

            if ($result) { return $result; }
        
        }

        /**
         * Verificação do estatuto de Revisor WISEflow
         *
         * @return bool
         */
        function isWFreviewer($usrNm)
        {
            global $DB;

            $result = $DB->get_records_sql(
                                           "SELECT  usr.id AS usrID,
                                                    usr.username AS usrNm,
                                                    rl.shortname AS role
                                            FROM mdl_context ctx
                                                INNER JOIN mdl_role_assignments rla ON rla.contextid = ctx.id
                                                INNER JOIN mdl_role rl ON rl.id = rla.roleid
                                                INNER JOIN mdl_user usr ON usr.id = rla.userid
                                            WHERE rl.shortname = 'wfrev'
                                                AND usr.username = '" . $usrNm . "'
                                            GROUP BY rl.shortname;"
                                          );

            if ($result) { return true; }

        }

        /**
         * Verificação do estatuto de Gestor WISEflow
         *
         * @return bool
         */
        function isWFmanager($usrNm)
        {
            global $DB;

            $result = $DB->get_records_sql(
                                           "SELECT usr.id AS usrID,
                                                   usr.username AS usrNm,
                                                   rl.shortname AS role
                                            FROM mdl_context ctx
                                                INNER JOIN mdl_role_assignments rla ON rla.contextid = ctx.id
                                                INNER JOIN mdl_role rl ON rl.id = rla.roleid
                                                INNER JOIN mdl_user usr ON usr.id = rla.userid
                                            WHERE rl.shortname = 'wfman'
                                                AND usr.username = '" . $usrNm . "'
                                            GROUP BY rl.shortname;"
                                          );

            if ($result) { return true; }

        }

        //verifica se o utilizador está devidamente autenticado e detém as permissões correctas
        if (isloggedin()
            && has_capability('block/sebenta:view', get_context_instance(CONTEXT_SYSTEM))) { //em caso afirmativo, constrói a interface
            $blkStyle = '<link href="../blocks/sebenta/style.css" rel="stylesheet" type="text/css" media="screen"/>';
            $blkScript = '<script src="../blocks/sebenta/script.js"></script>';
            $blkData = '';

            // verifica se o utilizador é gestor ou revisor WISEflow
            if (isWFmanager($USER->username) || isWFreviewer($USER->username)) {
                $modal = '<div id="myModal" class="modal fade" role="dialog">
                              <div class="modal-dialog">
                                  <div class="modal-content">
                                      <div class="modal-header">
                                          <button type="button" class="close" data-dismiss="modal">&times;</button>
                                          <h3 class="modal-title" id="flow_info">flow_info</h3>
                                      </div>
                                      <div class="modal-body" id="confirmation">
                                          <h4><b>Confirmar finalização do lançamento de notas?</b></h4>
                                          Desta operação, resultará:
                                              <ul>
                                                  <li>fim do período de avaliação</li>
                                                  <li>impossibilidade de alteração subsequente das notas</li>
                                                  <li>disponibilização das notas finais e comentários no WISEflow</li>
                                                  <li>disponibilização das notas finais no Cartão de Aprendizagem</li>
                                              </ul>
                                      </div>
                                      <div class="modal-footer" id="buttons">
                                          <button type="button" class="btn btn-secondary" id="btnCancel" data-dismiss="modal">Cancelar</button>
                                          <button type="submit" class="btn btn-primary" id="btnEndAssessDate"
                                                        onClick="endflowmarking(this.value, this.dataset.authChain, this.dataset.wfUrl)">
                                              Confirmar
                                          </button>
                                      </div>
                                  </div>
                              </div>
                          </div>';

                // copyright
                $devCR = "<div id='div_cr'>
                              <a title='desenvolvido por...'
                                  href='https://www.linkedin.com/in/brunomastavares/'
                                  target = '_blank'>
                                  &#xA9;2023
                              </a>
                          </div>";

                // if (has_capability('block/sebenta:myaddinstance', get_context_instance(CONTEXT_SYSTEM))) {
                if (isWFmanager($USER->username)) { // se gestor, tem acesso a todas as provas
                    $flwDoc = "&flwDoc=all";

                } else { // se revisor, tem acesso às provas que lhe estão atribuídas
                    $flwDoc = "&flwDoc=" . $USER->username;

                }

                // $this->title = 'WISEflow';

                $flwDocValue = isWFmanager($USER->username) ? 'all' : $USER->username;

                $blkData = $blkStyle
                         .'<div id="wiseflow"
                                data-role="teacher"
                                data-endpoint="' . $CFG->wwwroot . '/blocks/sebenta/fetch_flows.php"
                                data-flwdoc="' . htmlspecialchars($flwDocValue, ENT_QUOTES, 'UTF-8') . '"
                                data-sesskey="' . sesskey() . '"
                                data-initial-limit="4"
                                data-next-limit="10">
                               <div id="wiseflow_toolbar">
                                   <div id="wiseflow_title">WISEflow</div>
                                   <div id="wiseflow_status"></div>
                                   <button type="button" class="load-more-btn" id="wiseflow_load_more" title="carregar mais flows">+</button>
                               </div>
                               <div id="wiseflow_listwrap">
                                   <div id="wiseflow_list"></div>
                                   <div id="wiseflow_loading" class="sebenta-spinner"></div>
                                   <div id="wiseflow_sentinel" aria-hidden="true"></div>
                               </div>
                           </div>'
                         . $blkScript;

                $this->content->text = $blkData . $modal . $devCR;

            // verifica se o utilizador é estudante
            } elseif (null !== ($stdcrs = isStudent($USER->username))) {
                $crsLst = array();

                foreach ($stdcrs as $crs) {
                    if ($crsLst <> null) {
                        $crsLst .= ", '" . $crs->crsidn . "'";

                    } else {
                        $crsLst = "'" . $crs->crsidn . "'";

                    }

                }

                $stdasg = getAssignments($USER->username, $crsLst);

                // botão de navegação para a esquerda
                $blkData .= '<div class="sebenta_carousel-container" data-cache-key="sebenta.student.carousel.' . $USER->id . '">
                                 <button class="nav-button left" id="sebenta_prev">
                                     <svg class="fa-icon-nav" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                         <path d="M512 256A256 256 0 1 0 0 256a256 256 0 1 0 512 0zM271 135c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-87 87 87 87c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0L167 273c-9.4-9.4-9.4-24.6 0-33.9L271 135z"/>
                                     </svg>
                                 </button>
                                 <div class="sebenta_carousel" id="sebenta_carousel">';

                foreach ($stdcrs as $crs) {

                    // obtenção dos certificados via bloco Campus Virtual (lanca_pauta)
                    $lanca_pauta = new block_lanca_pauta();
                    $stdcert = $lanca_pauta->certificados($USER, get_course($crs->crsid), true);

                    $cert_valid = false;

                    if (($stdasg
                        && array_search($crs->crsid, $stdasg) >= 0)
                        && ($stdcert
                        && array_search($crs->crsid, array_column($stdcert, 'crsid')) >= 0)) { // se o estudante tem submissões e certificados no curso, gera o respectivo cartão no bloco
                        $blkData .=     '<div class="sebenta_carousel-item">
                                             <div class="sebenta_carousel-item-head">
                                                 <svg class="fa-icon-head" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                                     <path d="M64 464c-8.8 0-16-7.2-16-16L48 64c0-8.8 7.2-16 16-16l160 0 0 80c0 17.7 14.3 32 32 32l80 0 0 288c0 8.8-7.2 16-16 16L64 464zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-293.5c0-17-6.7-33.3-18.7-45.3L274.7 18.7C262.7 6.7 246.5 0 229.5 0L64 0zm56 256c-13.3 0-24 10.7-24 24s10.7 24 24 24l144 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-144 0zm0 96c-13.3 0-24 10.7-24 24s10.7 24 24 24l144 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-144 0z"/>
                                                 </svg>
                                             </div>
                                             <div class="sebenta_carousel-item-body">
                                                 <p class="code">UC ' . $crs->crsidn . '</p>
                                                 <p class="title">(' . $crs->crsname . ')</p>
                                                 <hr>
                                                 <table class="tg">
                                                     <thead>
                                                         <tr>
                                                             <th>item</th>
                                                             <th>nota</th>
                                                         </tr>
                                                     </thead>
                                                     <tbody>';

                        foreach ($stdcert as $cert) {

                            // certificados de submissões na PlataformAbERTA
                            foreach ($stdasg as $asg) {
                                if ($asg->crsid == $crs->crsid && $asg->assign == $cert['idn'] && $cert['form'] <> '') {
                                    $grade = $asg->grade ? $asg->grade : 0;

                                    $blkData .=         '<tr>
                                                             <td class="tg-0lax" title="declaração electrónica">' . base64_decode($cert['form']) . '</td>
                                                             <td class="tg-0lax">' . round($grade, 2) . '</td>
                                                         </tr>';

                                    $cert_valid = true;

                                }

                            }

                            // certificados de submissões na WISEflow
                            if (preg_match("/^(E|X)(N|R|E)$/", $cert['idn']) && $cert['form'] <> '') {
                                $grade = $cert['grade'] ? $cert['grade'] : 0;

                                $blkData .=             '<tr>
                                                             <td class="tg-0lax" title="declaração electrónica">' . base64_decode($cert['form']) . '</td>
                                                             <td class="tg-0lax">' . round($grade, 2) . '</td>
                                                         </tr>';

                                $cert_valid = true;

                            }

                        }

                        if (!$cert_valid) {
                            $blkData .=                 '<tr>
                                                             <td class="tg-0lax" colspan="2" style="padding-top: 20px !important; color: red;">(certificado inválido)</td>
                                                         </tr>';

                        }

                        $blkData .=                 '</tbody>
                                                 </table>
                                             </div>
                                         </div>';

                    }

                }

                // botão de navegação para a direita
                $blkData .=     '</div>
                                 <div id="sebenta_dots" aria-label="navegacao do carrossel"></div>
                                 <button class="nav-button right" id="sebenta_next">
                                     <svg class="fa-icon-nav" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                         <path d="M0 256a256 256 0 1 0 512 0A256 256 0 1 0 0 256zM241 377c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l87-87-87-87c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0L345 239c9.4 9.4 9.4 24.6 0 33.9L241 377z"/>
                                     </svg>
                                 </button>
                             </div>';

                $blkData = $blkStyle . $blkData . $blkScript;

                // $this->title = 'Sebenta';

                $this->content->text = '<div id="sebenta">' . $blkData . '</div>';

            } else { //em caso negativo, não exibe qualquer conteúdo
                $this->content = '';

            }

        } else { //em caso negativo, não exibe qualquer conteúdo
            $this->content = '';

        }

        return $this->content;

    }

}
