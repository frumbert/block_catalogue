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
 * Settings for the catalogue block
 *
 * @package    block_catalogue
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot . '/blocks/catalogue/lib.php');

    // Presentation options heading.
    $settings->add(new admin_setting_heading('block_catalogue/appearance',
            get_string('appearance', 'admin'),
            ''));

    // Display Course Categories on Dashboard course items (cards, lists, summary items).
    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/displaycategories',
            get_string('displaycategories', 'block_catalogue'),
            get_string('displaycategories_help', 'block_catalogue'),
            1));

    $settings->add(new admin_setting_configtext(
      'block_catalogue/topcategoryid',
      get_string('setting:topcategoryid','block_catalogue'),
      get_string('setting:topcategoryid_desc','block_catalogue'),
      '0',
      PARAM_INT));

    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/showsortbycategories',
            get_string('setting:sortbycategories', 'block_catalogue'),
            '',
            0));

    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/showtags',
            get_string('setting:showtags', 'block_catalogue'),
            '',
            0));

    // Enable / Disable available layouts.
    $choices = array(BLOCK_CATALOGUE_VIEW_CARD => get_string('card', 'block_catalogue'),
            BLOCK_CATALOGUE_VIEW_LIST => get_string('list', 'block_catalogue'),
            BLOCK_CATALOGUE_VIEW_SUMMARY => get_string('summary', 'block_catalogue'));
    $settings->add(new admin_setting_configmulticheckbox(
            'block_catalogue/layouts',
            get_string('layouts', 'block_catalogue'),
            get_string('layouts_help', 'block_catalogue'),
            $choices,
            $choices));
    unset ($choices);

    // Enable / Disable course filter items.
    $settings->add(new admin_setting_heading('block_catalogue/availablegroupings',
            get_string('availablegroupings', 'block_catalogue'),
            get_string('availablegroupings_desc', 'block_catalogue')));

    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/displaygroupingallincludinghidden',
            get_string('allincludinghidden', 'block_catalogue'),
            '',
            0));

    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/displaygroupingall',
            get_string('all', 'block_catalogue'),
            '',
            1));

    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/displaygroupinginprogress',
            get_string('inprogress', 'block_catalogue'),
            '',
            1));

    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/displaygroupingpast',
            get_string('past', 'block_catalogue'),
            '',
            1));

    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/displaygroupingfuture',
            get_string('future', 'block_catalogue'),
            '',
            1));

    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/displaygroupingcustomfield',
            get_string('customfield', 'block_catalogue'),
            '',
            0));

    $choices = \core_customfield\api::get_fields_supporting_course_grouping();
    if ($choices) {
        $choices  = ['' => get_string('choosedots')] + $choices;
        $settings->add(new admin_setting_configselect(
                'block_catalogue/customfiltergrouping',
                get_string('customfiltergrouping', 'block_catalogue'),
                '',
                '',
                $choices));
    } else {
        $settings->add(new admin_setting_configempty(
                'block_catalogue/customfiltergrouping',
                get_string('customfiltergrouping', 'block_catalogue'),
                get_string('customfiltergrouping_nofields', 'block_catalogue')));
    }
    $settings->hide_if('block_catalogue/customfiltergrouping', 'block_catalogue/displaygroupingcustomfield');

    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/displaygroupingfavourites',
            get_string('favourites', 'block_catalogue'),
            '',
            1));

    $settings->add(new admin_setting_configcheckbox(
            'block_catalogue/displaygroupinghidden',
            get_string('hiddencourses', 'block_catalogue'),
            '',
            1));
}
