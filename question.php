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
require_once($CFG->dirroot . '/question/type/wq/question.php');
require_once($CFG->dirroot . '/question/type/wq/step.php');

class qtype_shortanswerwiris_question extends qtype_wq_question
        implements question_automatically_gradable, question_response_answer_comparer {

    const LOCALDATA_NAME_INPUT_FIELD_TYPE = "inputField";
    const LOCALDATA_VALUE_INPUT_FIELD_TYPE_GRAPH = "inlineGraph";
    const LOCALDATA_VALUE_INPUT_FIELD_TYPE_TEXT = "textField";
    
    const LOCALDATA_NAME_COMPOUND_ANSWER = "inputCompound";
    const LOCALDATA_VALUE_COMPOUND_ANSWER_TRUE = "true";

    /**
     * A link to last question attempt step and also a helper class for some
     * grading issues.
     */
    public $step;

    /**
     * reference to Moodle's shortanswer question fields.
     */
    public $answers;

    public function __construct(question_definition $base = null) {
        parent::__construct($base);
        $this->step = new qtype_wirisstep();
    }
    public function start_attempt(question_attempt_step $step, $variant) {
        parent::start_attempt($step, $variant);
        $this->step->load($step);
    }
    public function apply_attempt_state(question_attempt_step $step) {
        parent::apply_attempt_state($step);
        $this->step->load($step);
        if ($this->step->is_first_step()) {
            // This is a regrade because is the only case where this function is
            // called with the first step instead of start_attempt. So invalidate
            // cached matching answers.
            $this->step->set_var('_response_hash', '0');
        }
    }
    /**
     * @return All the text of the question in a single string so Wiris Quizzes
     * can extract the variable placeholders.
     */
    public function join_all_text() {
        $text = parent::join_all_text();
        // Only feedback: answers should be extracted using newVariablesRequestWithQuestionData.
        foreach ($this->base->answers as $key => $value) {
            $text .= ' ' . $value->feedback;
        }
        return $text;
    }

    /**
     *
     * @return String Return the general feedback text in a single string so Wiris
     * quizzes can extract the variable placeholders.
     */
    public function join_feedback_text() {
        $text = parent::join_feedback_text();
        // Answer feedback.
        foreach ($this->base->answers as $key => $value) {
            $text .= ' ' . $value->feedback;
        }

        return $text;
    }

    public function grade_response(array $response) {
        $answer = $this->get_matching_answer($response);
        if ($answer) {
            $fraction = 0.0;

            $grade = $this->step->get_var('_matching_answer_grade');
            if (!empty($grade)) {
                $fraction = $grade;
            }

            $state = question_state::graded_state_for_fraction($fraction);
            return array($fraction, $state);
        } else if ($this->step->is_error()) {
            // Do not grade and tell teacher to do so...
            return array(null, question_state::$needsgrading);
        } else {
            return array(0, question_state::$gradedwrong);
        }
    }

    public function get_matching_answer(array $response) {
        try {
            // Quick return if no answer given.
            if (!isset($response['answer']) || $response['answer'] === null) {
                return null;
            }
            // Optimization in order to avoid a service call.
            $responsehash = md5($response['answer']);
            if ($this->step->get_var('_response_hash') == $responsehash) {
                $matchinganswer = $this->step->get_var('_matching_answer');
                if (!empty($matchinganswer)) {
                    return $this->base->answers[$matchinganswer];
                } else if (!is_null($matchinganswer)) {
                    return null;
                }
            }

            // Security protection:
            // The same question should not be graded more than N times with failure.
            if ($this->step->is_attempt_limit_reached()) {
                return null;
            }

            $slot = array(); 
            $slot['studentAnswer'] = $response['answer'];
            
            $authorAnswers = array();
            foreach ($this->base->answers as $answer) {
                $authorAnswer = array();
                $authorAnswer['value'] = $answer->answer;
                $authorAnswer['feedback'] = $answer->feedback;
                $authorAnswer['fraction'] = $answer->fraction;
                $authorAnswers[] = $authorAnswer;
            }
            $slot['authorAnswers'] = $authorAnswers;

            $slots = array();
            $slots[] = $slot; // There is a single slot in a short answer question.
            
            $response = $this->call_grade_service($slots);

            $this->wirisquestioninstancexml = $response->{'instance'};

            $gradedSlots = $response->{'gradedSlots'};
            $gradedSlot = $gradedSlots[0]; // A single slot, again.
            $matchinganswerid = $gradedSlot->{'matchingAuthorAnswer'} ?? 0;
            $grade = $gradedSlot->{'grade'} ?? 0.0;

            $this->step->set_var('_matching_answer_grade', $grade, true);
            $this->step->set_var('_matching_answer', $matchinganswerid, true);
            $this->step->set_var('_response_hash', $responsehash, true);
            $this->step->set_var('_qi', $this->wirisquestioninstancexml, true);
            $this->step->reset_attempts();

            return $answer;
        } catch (moodle_exception $e) {
            // Notify of the error.
            $this->step->inc_attempts();
            throw $e;
        }
    }

    public function summarise_response(array $response) {
        // This function must return plain text output. Since student response
        // may be mathml and the conversion MathML => text made in
        // expand_variables_text() is not good, we prevent to show incorrect
        // data.
        if (!$this->is_text_answer()) {
            return get_string('contentnotviewable', 'qtype_shortanswerwiris');
        } else {
            return parent::summarise_response($response);
        }
    }

    public function get_right_answer_summary() {
        return get_string('contentnotviewable', 'qtype_shortanswerwiris');
    }

    public function format_answer($text) {
        if ($this->is_text_answer() && !$this->is_compound_answer()) {
            $text = $this->expand_variables_text($text);
        } else if (!$this->is_graphical_answer()) {
            $text = $this->expand_variables_mathml($text);
        }

        return $text;
    }

    private function is_text_answer() {
        $inputfield = $this->get_local_data_from_question(self::LOCALDATA_NAME_INPUT_FIELD_TYPE);
        return $inputfield == self::LOCALDATA_VALUE_INPUT_FIELD_TYPE_TEXT;
    }

    private function is_graphical_answer() {
        $inputfield = $this->get_local_data_from_question(self::LOCALDATA_NAME_INPUT_FIELD_TYPE);
        return $inputfield == self::LOCALDATA_VALUE_INPUT_FIELD_TYPE_GRAPH;
    }

    private function is_compound_answer() {
        $iscompound = $this->get_local_data_from_question(self::LOCALDATA_NAME_COMPOUND_ANSWER);
        return $iscompound == self::LOCALDATA_VALUE_COMPOUND_ANSWER_TRUE;
    }

    public function get_correct_response() {
        // We need to replace all aterisk for scaped asterisks:
        // Because shortanswer get_correct_response() methods
        // cleans all asterisks, asterisks are shortanswer wildcards.
        // However on Wiris shortanswers asterisk means product.
        foreach ($this->answers as $key => $value) {
            $this->answers[$key]->answer = str_replace('*', '\*', $value->answer);
        }

        $correct = parent::get_correct_response();
        $correct['answer'] = $this->format_answer($correct['answer']);
        return $correct;
    }
}
