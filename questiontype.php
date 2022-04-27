<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/wq/questiontype.php');
require_once($CFG->dirroot . '/question/type/shortanswer/questiontype.php');

class qtype_shortanswerwiris extends qtype_wq {

    public function __construct() {
        parent::__construct(new qtype_shortanswer());
    }

    public function extra_question_fields() {
        return array('qtype_shortanswer_options', 'usecase');
    }

    public function create_editing_form($submiturl, $question, $category, $contexts, $formeditable) {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/shortanswerwiris/edit_shortanswerwiris_form.php');
        $wform = new qtype_shortanswerwiris_helper_edit_form($submiturl, $question, $category, $contexts, $formeditable);
        return new qtype_shortanswerwiris_edit_form($wform, $submiturl, $question, $category, $contexts, $formeditable);
    }
    public function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->answers = &$question->base->answers;
    }

    public function export_to_xml($question, qformat_xml $format, $extra=null) {
        $expout = "    <usecase>{$question->options->usecase}</usecase>\n";
        $expout .= $format->write_answers($question->options->answers);
        $expout .= parent::export_to_xml($question, $format);
        return $expout;
    }

    public function import_from_xml($data, $question, qformat_xml $format, $extra=null) {
        if (isset($question) && $question == 0) {
            return false;
        }

        // Import from Moodle > 2.x.
        $qo = $format->import_shortanswer($data);
        $qo->qtype = 'shortanswerwiris';
        $qo->wirisquestion = trim($this->decode_html_entities($data['#']['wirisquestion'][0]['#']));
        return $qo;
    }

}
