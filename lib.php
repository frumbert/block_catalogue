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

/**
 * Library functions for overview.
 *
 * @package   block_catalogue
 * @copyright 2018 Peter Dias
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Constants for the user preferences grouping options
 */
define('BLOCK_CATALOGUE_GROUPING_ALLINCLUDINGHIDDEN', 'allincludinghidden');
define('BLOCK_CATALOGUE_GROUPING_ALL', 'all');
define('BLOCK_CATALOGUE_GROUPING_INPROGRESS', 'inprogress');
define('BLOCK_CATALOGUE_GROUPING_FUTURE', 'future');
define('BLOCK_CATALOGUE_GROUPING_PAST', 'past');
define('BLOCK_CATALOGUE_GROUPING_FAVOURITES', 'favourites');
define('BLOCK_CATALOGUE_GROUPING_HIDDEN', 'hidden');
define('BLOCK_CATALOGUE_GROUPING_CUSTOMFIELD', 'customfield');

/**
 * Allows selection of all courses without a value for the custom field.
 */
// define('BLOCK_CATALOGUE_CUSTOMFIELD_EMPTY', -1);

/**
 * Constants for the user preferences sorting options
 * timeline
 */
define('BLOCK_CATALOGUE_SORTING_TITLE', 'title');
define('BLOCK_CATALOGUE_SORTING_LASTACCESSED', 'lastaccessed');
define('BLOCK_CATALOGUE_SORTING_SHORTNAME', 'shortname');
define('BLOCK_CATALOGUE_SORTING_PUBLISHED', 'published');
define('BLOCK_CATALOGUE_SORTING_DEFAULT', 'default');

/**
 * Constants for the user preferences view options
 */
define('BLOCK_CATALOGUE_VIEW_CARD', 'card');
define('BLOCK_CATALOGUE_VIEW_LIST', 'list');
define('BLOCK_CATALOGUE_VIEW_SUMMARY', 'summary');

/**
 * Constants for the user paging preferences
 */
define('BLOCK_CATALOGUE_PAGING_12', 12);
define('BLOCK_CATALOGUE_PAGING_24', 24);
define('BLOCK_CATALOGUE_PAGING_48', 48);
define('BLOCK_CATALOGUE_PAGING_96', 96);
define('BLOCK_CATALOGUE_PAGING_ALL', 0);

/**
 * Constants for the admin category display setting
 */
define('BLOCK_CATALOGUE_DISPLAY_CATEGORIES_ON', 'on');
define('BLOCK_CATALOGUE_DISPLAY_CATEGORIES_OFF', 'off');

/**
 * Get the current user preferences that are available
 *
 * @uses core_user::is_current_user
 *
 * @return array[] Array representing current options along with defaults
 */
function block_catalogue_user_preferences(): array {
    $preferences['block_catalogue_user_grouping_preference'] = array(
        'null' => NULL_NOT_ALLOWED,
        'default' => BLOCK_CATALOGUE_GROUPING_ALL,
        'type' => PARAM_ALPHA,
        'choices' => array(
            BLOCK_CATALOGUE_GROUPING_ALLINCLUDINGHIDDEN,
            BLOCK_CATALOGUE_GROUPING_ALL,
            BLOCK_CATALOGUE_GROUPING_INPROGRESS,
            BLOCK_CATALOGUE_GROUPING_FUTURE,
            BLOCK_CATALOGUE_GROUPING_PAST,
            BLOCK_CATALOGUE_GROUPING_FAVOURITES,
            BLOCK_CATALOGUE_GROUPING_HIDDEN,
            BLOCK_CATALOGUE_GROUPING_CUSTOMFIELD,
        ),
        'permissioncallback' => [core_user::class, 'is_current_user'],
    );

    $preferences['block_catalogue_user_grouping_customfieldvalue_preference'] = [
        'null' => NULL_ALLOWED,
        'default' => null,
        'type' => PARAM_RAW,
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];

    // TIM - category chooser must be defined
    $preferences['block_catalogue_user_filter_category'] = [
        'null' => NULL_ALLOWED,
        'default' => null,
        'type' => PARAM_INT,
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];

    $preferences['block_catalogue_user_sort_preference'] = array(
        'null' => NULL_NOT_ALLOWED,
        'default' => BLOCK_CATALOGUE_SORTING_LASTACCESSED,
        'type' => PARAM_ALPHA,
        'choices' => array(
            BLOCK_CATALOGUE_SORTING_TITLE,
            BLOCK_CATALOGUE_SORTING_LASTACCESSED,
            BLOCK_CATALOGUE_SORTING_SHORTNAME,
            BLOCK_CATALOGUE_SORTING_PUBLISHED,
            BLOCK_CATALOGUE_SORTING_DEFAULT
        ),
        'permissioncallback' => [core_user::class, 'is_current_user'],
    );

    $preferences['block_catalogue_user_view_preference'] = array(
        'null' => NULL_NOT_ALLOWED,
        'default' => BLOCK_CATALOGUE_VIEW_CARD,
        'type' => PARAM_ALPHA,
        'choices' => array(
            BLOCK_CATALOGUE_VIEW_CARD,
            BLOCK_CATALOGUE_VIEW_LIST,
            BLOCK_CATALOGUE_VIEW_SUMMARY
        ),
        'permissioncallback' => [core_user::class, 'is_current_user'],
    );

    $preferences['/^block_catalogue_hidden_course_(\d)+$/'] = array(
        'isregex' => true,
        'choices' => array(0, 1),
        'type' => PARAM_INT,
        'null' => NULL_NOT_ALLOWED,
        'default' => 0,
        'permissioncallback' => [core_user::class, 'is_current_user'],
    );

    $preferences['block_catalogue_user_paging_preference'] = array(
        'null' => NULL_NOT_ALLOWED,
        'default' => BLOCK_CATALOGUE_PAGING_12,
        'type' => PARAM_INT,
        'choices' => array(
            BLOCK_CATALOGUE_PAGING_12,
            BLOCK_CATALOGUE_PAGING_24,
            BLOCK_CATALOGUE_PAGING_48,
            BLOCK_CATALOGUE_PAGING_96,
            BLOCK_CATALOGUE_PAGING_ALL
        ),
        'permissioncallback' => [core_user::class, 'is_current_user'],
    );

    return $preferences;
}

/**
 * Pre-delete course hook to cleanup any records with references to the deleted course.
 *
 * @param stdClass $course The deleted course
 */
function block_catalogue_pre_course_delete(\stdClass $course) {
    // Removing any favourited courses which have been created for users, for this course.
    $service = \core_favourites\service_factory::get_service_for_component('core_course');
    $service->delete_favourites_by_type_and_item('courses', $course->id);
}
