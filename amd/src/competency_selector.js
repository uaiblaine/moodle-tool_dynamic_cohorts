// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Competency search for the competency proficiency condition's autocomplete.
 *
 * @module     tool_dynamic_cohorts/competency_selector
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Search competencies for the autocomplete.
 *
 * The context is the page's, as the condition form fragment's is: the web service decides from it
 * which frameworks may be offered.
 *
 * @param {String} selector Selector of the original select element.
 * @param {String} query Search text.
 * @param {Function} success Called with the results.
 * @param {Function} failure Called with an error.
 */
export const transport = (selector, query, success, failure) => {
    Ajax.call([{
        methodname: 'tool_dynamic_cohorts_search_competencies',
        args: {query: query, contextid: M.cfg.contextid},
    }])[0].then(success).catch(failure);
};

/**
 * Map results to autocomplete options; labels arrive escaped, as the autocomplete inserts them as HTML.
 *
 * @param {String} selector Selector of the original select element.
 * @param {Array} results Results of the web service.
 * @returns {Array}
 */
export const processResults = (selector, results) => results.map(result => ({value: result.id, label: result.label}));
