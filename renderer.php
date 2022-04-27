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
require_once($CFG->dirroot . '/question/type/shortanswer/renderer.php');
require_once($CFG->dirroot . '/question/type/wq/renderer.php');

class qtype_shortanswerwiris_renderer extends qtype_wq_renderer {
    public function __construct(moodle_page $page, $target) {
        parent::__construct(new qtype_shortanswer_renderer($page, $target), $page, $target);
    }

    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {

        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');

        $inputname = $qa->get_qt_field_name('answer');

        $inputattributes = array(
            'type' => 'text',
            'name' => $inputname,
            'value' => $currentanswer,
            'id' => $inputname,
            'size' => 80,
            'style' => 'display:none;',
            'class' => 'wirisanswerfield',
        );
        if ($options->readonly) {
            $inputattributes['class'] .= ' wirisreadonly';
            $inputattributes['readonly'] = 'readonly';
        }

        if ($options->correctness) {
            $answer = $question->get_matching_answer(array('answer' => $currentanswer));
            $fraction = $qa->get_last_qt_var('_matching_answer_grade') ?? 0;
            $inputattributes['class'] .= ' wirisembeddedfeedback ' . $this->feedback_class($fraction);
            // Feedback image delegate to wirisembeddedfeedback class.
        }

        $questiontext = $question->format_questiontext($qa);

        $input = html_writer::empty_tag('input', $inputattributes);

        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= $this->auxiliar_cas();
        $result .= html_writer::tag('label', get_string('answer', 'qtype_shortanswer',
                html_writer::tag('span', $input, array('class' => 'answer'))), array('for' => $inputattributes['id']));
        $result .= html_writer::end_tag('div');

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error(array('answer' => $currentanswer)), array('class' => 'validationerror'));
        }

        $result .= $this->add_auxiliary_text($qa, $options);
        $result .= $this->add_javascript();
        $result .= $this->lang();
        $result .= $this->question($qa);
        $result .= $this->question_instance($qa);

        return $result;
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();
        $answer = $question->get_correct_response();
        if (!$answer) {
            return '';
        }

        $filterableanswer = $this->answer_to_filterable_value($answer['answer']);
        $text = get_string('correctansweris', 'qtype_shortanswer', $filterableanswer);
        return $question->format_text($text, FORMAT_HTML, $qa, 'question', 'correctanswer', $question->id);
    }

    private function answer_to_filterable_value($answer) {
        $json = json_decode($answer); // Try to decode as JSON to check if it's a GeometryFile.
        if ($json != null & isset($json['elements'])) {
            // The only case we need to handle is to transform Geometry File(s) into their filterable value (this is parsed
            // & replaced by quizzes.js). MathML is parsed by the wiris filter.
            return "<img " .
                    "src=\"" . $CFG->wwwroot . "/question/type/wq/quizzes/service.php?name=plotter_loading.png&service=resource\" " .
                    "alt=\"Plotter\" " .
                    "class=\"wirisconstruction wirisgraphanimate\" " .
                    "data-wirisconstruction=\"" . htmlspecialchars($answer) . "\"" .
                    "/>";
        }
        return $answer;
    }

    public function feedback_class($fraction) {
        return 'wiris' . parent::feedback_class($fraction);
    }
}
