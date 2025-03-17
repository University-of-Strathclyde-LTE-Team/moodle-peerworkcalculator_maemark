<?php
// This file is part of a 3rd party created module for Moodle - http://moodle.org/
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

/**
 * WebPA calculator.
 *
 * @package    peerworkcalculator_maemark_cf
 * @copyright  2025 CT
 * @author     Chris Triantafyllou <chris.triantafyllou@strath.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace peerworkcalculator_maemark_cf;

use mod_peerwork\pa_result;
use mod_peerwork\peerworkcalculator_plugin;

/**
 * MAEMark CF calculator.
 *
 * @package    peerworkcalculator_maemark_cf
 * @copyright  2025 CT
 * @author     Chris Triantafyllou <chris.triantafyllou@strath.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calculator extends peerworkcalculator_plugin {

    /**
     * Get the name of the custom calculator plugin
     *
     * @return string
     */
    public function get_name() {
        return get_string('webpa', 'peerworkcalculator_maemark_cf');
    }

    /**
     * Calculate.
     *
     * Each member of the group must have an associated key in the $grades,
     * under which an array of the grades they gave to other members indexed
     * by member ID.
     *
     * In the example below, Alice rated Bob 4, and Elaine did not submit any marks..
     *
     * [
     *  'alice' => [
     *       'alice' => [2,2],
     *       'bob' => [1,3],
     *       'claire' => [3, 0],
     *       'david' => [1,1],
     *       'elaine' => [1,0]
     *   ],
     *   'bob' => [
     *       'alice' => [2,1],
     *       'bob' => [2,3],
     *       'claire' => [2,1],
     *       'david' => [1,1],
     *       'elaine' => [0,0]
     *   ],
     *   'claire' => [
     *       'alice' => [2,2],
     *       'bob' => [2,2],
     *       'claire' => [2,2],
     *       'david' => [2,2],
     *       'elaine' => [2,2]
     *   ],
     *   'david' => [
     *       'alice' => [2,1],
     *       'bob' => [3,2],
     *       'claire' => [2,2],
     *       'david' => [1,2],
     *       'elaine' => [1,0]
     *   ],
     *   'elaine' => []
     * ];
     *
     * @param array $grades The list of marks given.
     * @param array $noncompletionfactor The non-completion mask to fix the calculation issue.
     * @param int $groupmark The mark given to the group.
     * @param int $noncompletionpenalty The penalty to be applied.
     * @param int $paweighting The weighting to be applied.
     * @param bool $selfgrade If self grading is enabled.
     * @return pa_result.
     */
    public function calculate($grades, $groupmark, $noncompletionpenalty = 0, $paweighting = 1, $selfgrade = false) {
        $memberids = array_keys($grades);
        $totalscores = [];
        $fracscores = [];
        $noncompletionfactor = [];
        $numsubmitted = 0;

        // Work out a non-completion mask
        $noncompletionfactor = array_map(function($gradesgiven) {
            return empty($gradesgiven) ? 0 : 1;
        }, $grades);


        // Calculate the total scores.
        foreach ($memberids as $memberid) {
            foreach ($grades as $graderid => $gradesgiven) {
                if (!isset($totalscores[$graderid])) {
                    $totalscores[$graderid] = [];
                }

                if (isset($gradesgiven[$memberid])) {
                    $sum = array_reduce($gradesgiven[$memberid], function($carry, $item) use ($noncompletionfactor) {
                        //$carry += $item;
                        $carry += $item;
                        return $carry;
                    });

                    $totalscores[$graderid][$memberid] = $sum * $noncompletionfactor[$memberid];
                }
            }
        }

        // Calculate the fractional scores, and record whether scores were submitted.
        foreach ($memberids as $memberid) {
            $gradesgiven = $totalscores[$memberid];
            $total = array_sum($gradesgiven);

            $fracscores[$memberid] = array_reduce(array_keys($gradesgiven), function($carry, $peerid) use ($total, $gradesgiven) {
                $grade = $gradesgiven[$peerid];
                $carry[$peerid] = $total > 0 ? $grade / $total : 0;
                //$carry[$peerid] = $total > 0 ? $grade * $noncompletionfactor[$memberid] / $total : 0;
                return $carry;
            }, []);

            $numsubmitted += !empty($fracscores[$memberid]) ? 1 : 0;
        }

        // Initialise everyone's score at 0.
        $webpascores = array_reduce($memberids, function($carry, $memberid) {
            $carry[$memberid] = 0;
            return $carry;
        }, []);

        // Walk through the individual scores given, and sum them up.
        foreach ($fracscores as $gradesgiven) {
            foreach ($gradesgiven as $memberid => $fraction) {
                $webpascores[$memberid] += $fraction;
            }
        }

        //webpascores are the contribution factors

        // Apply the fudge factor to all scores received. (Obsolete)
        $nummembers = count($memberids);
        //$numgraders = count($graderids);

        //$fudgefactor = $numsubmitted > 0 ? $nummembers / $numsubmitted : 1;
        // Simple override to ignore fudge factor effects.
        $fudgefactor = 1;
        $webpascores = array_map(function($grade) use ($fudgefactor) {
            return $grade * $fudgefactor;
        }, $webpascores);

        // Calculate the students' preliminary grade (excludes weighting and penalties).
        $prelimgrades = array_map(function($score) use ($groupmark) {
            return max(0, min(100, $score * $groupmark));
        }, $webpascores);

        // Calculate penalties.
        $noncompletionpenalties = array_reduce($memberids, function($carry, $memberid) use ($fracscores, $noncompletionpenalty) {
            $ispenalised = empty($fracscores[$memberid]);
            $carry[$memberid] = $ispenalised ? $noncompletionpenalty : 0;
            return $carry;
        });

        // Calculate the grades again, but with weighting and penalties.
        $grades = array_reduce(
            $memberids,
            function($carry, $memberid) use ($webpascores, $noncompletionpenalties, $groupmark, $paweighting, $noncompletionfactor, $total) {
                $score = $webpascores[$memberid];

                $adjustedgroupmark = $groupmark * $paweighting;
                $automaticgrade = $groupmark - $adjustedgroupmark;
                $grade = max(0, min(100, $automaticgrade + ($score * $adjustedgroupmark)));

                $penaltyamount = $noncompletionpenalties[$memberid];
                if ($penaltyamount > 0) {
                    $grade = $groupmark - ($penaltyamount * 100);
                }

                //$carry[$memberid] = $grade;
                //$carry[$memberid] = $noncompletionfactor[$memberid];
                $carry[$memberid] = $webpascores[$memberid];

                return $carry;
            },
            []);

        return new pa_result($fracscores, $webpascores, $prelimgrades, $grades, $noncompletionpenalties);
    }

    /**
     * Function to return if calculation uses paweighting.
     *
     * @return bool
     */
    public static function usespaweighting() {
        return true;
        //Should be true if exporting $grade, otherwise it can be either T/F
    }
}
