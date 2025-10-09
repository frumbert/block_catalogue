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
 * Class containing data for my overview block.
 *
 * @package    block_catalogue
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_catalogue\output;
defined('MOODLE_INTERNAL') || die();

use core_competency\url;
use renderable;
use renderer_base;
use templatable;
use stdClass;

require_once($CFG->dirroot . '/blocks/myoverview/lib.php'); // needs this so customfield renderers can access constants
require_once($CFG->dirroot . '/blocks/catalogue/lib.php');

/**
 * Class containing data for my overview block.
 *
 * @copyright  2018 Bas Brands <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {

    /**
     * Store the grouping preference.
     *
     * @var string String matching the grouping constants defined in catalogue/lib.php
     */
    private $grouping;

    /**
     * Store the sort preference.
     *
     * @var string String matching the sort constants defined in catalogue/lib.php
     */
    private $sort;

    /**
     * Store the view preference.
     *
     * @var string String matching the view/display constants defined in catalogue/lib.php
     */
    private $view;

    /**
     * Store the paging preference.
     *
     * @var string String matching the paging constants defined in catalogue/lib.php
     */
    private $paging;

    /**
     * Store the display categories config setting.
     *
     * @var boolean
     */
    private $displaycategories;
    private $showsortbycategories;
    private $currentcategory;

    /**
     * Store the configuration values for the catalogue block.
     *
     * @var array Array of available layouts matching view/display constants defined in catalogue/lib.php
     */
    private $layouts;

    /**
     * Store a course grouping option setting
     *
     * @var boolean
     */
    private $displaygroupingallincludinghidden;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupingall;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupinginprogress;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupingfuture;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupingpast;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupingfavourites;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupinghidden;

    /**
     * Store a course grouping option setting.
     *
     * @var bool
     */
    private $displaygroupingcustomfield;

    /**
     * Store the custom field used by customfield grouping.
     *
     * @var string
     */
    private $customfiltergrouping;

    /**
     * Store the selected custom field value to group by.
     *
     * @var string
     */
    private $customfieldvalue;

    /** @var bool true if grouping selector should be shown, otherwise false. */
    protected $displaygroupingselector;

    /** @var int id of parent category for listing courses in. */
    private $topcategoryid;

    /**
     * main constructor.
     * Initialize the user preferences
     *
     * @param string $grouping Grouping user preference
     * @param string $sort Sort user preference
     * @param string $view Display user preference
     * @param int $paging
     * @param string $customfieldvalue
     *
     * @throws \dml_exception
     */
    public function __construct($grouping, $sort, $view, $paging, $customfieldvalue = null, $category = null) {
        global $CFG;
        // Get plugin config.
        $config = get_config('block_catalogue');

        $this->topcategoryid = $config->topcategoryid ?? 0;
        $this->currentcategory = $category;

        $this->showsortbycategories = ($config->showsortbycategories == "1");

        // Build the course grouping option name to check if the given grouping is enabled afterwards.
        $groupingconfigname = 'displaygrouping'.$grouping;
        // Check the given grouping and remember it if it is enabled.
        if ($grouping && $config->$groupingconfigname == true) {
            $this->grouping = $grouping;

            // Otherwise fall back to another grouping in a reasonable order.
            // This is done to prevent one-time UI glitches in the case when a user has chosen a grouping option previously which
            // was then disabled by the admin in the meantime.
        } else {
            $this->grouping = $this->get_fallback_grouping($config);
        }
        unset ($groupingconfigname);

        // Remember which custom field value we were using, if grouping by custom field.
        $this->customfieldvalue = $customfieldvalue;

        // Check and remember the given sorting.
        if ($sort) {
            $this->sort = $sort;
        } else if ($CFG->courselistshortnames) {
            $this->sort = BLOCK_CATALOGUE_SORTING_SHORTNAME;
        } else {
            $this->sort = BLOCK_CATALOGUE_SORTING_TITLE;
        }
        // In case sorting remembered is shortname and display extended course names not checked,
        // we should revert sorting to title.
        if (!$CFG->courselistshortnames && $sort == BLOCK_CATALOGUE_SORTING_SHORTNAME) {
            $this->sort = BLOCK_CATALOGUE_SORTING_TITLE;
        }

        // Check and remember the given view.
        $this->view = $view ? $view : BLOCK_CATALOGUE_VIEW_CARD;

        // Check and remember the given page size, `null` indicates no page size set
        // while a `0` indicates a paging size of `All`.
        if (!is_null($paging) && $paging == BLOCK_CATALOGUE_PAGING_ALL) {
            $this->paging = BLOCK_CATALOGUE_PAGING_ALL;
        } else {
            $this->paging = $paging ? $paging : BLOCK_CATALOGUE_PAGING_12;
        }

        // Check and remember if the course categories should be shown or not.
        if (!$config->displaycategories) {
            $this->displaycategories = BLOCK_CATALOGUE_DISPLAY_CATEGORIES_OFF;
        } else {
            $this->displaycategories = BLOCK_CATALOGUE_DISPLAY_CATEGORIES_ON;
        }

        // Get and remember the available layouts.
        $this->set_available_layouts();
        $this->view = $view ? $view : reset($this->layouts);

        // Check and remember if the particular grouping options should be shown or not.
        $this->displaygroupingallincludinghidden = $config->displaygroupingallincludinghidden;
        $this->displaygroupingall = $config->displaygroupingall;
        $this->displaygroupinginprogress = $config->displaygroupinginprogress;
        $this->displaygroupingfuture = $config->displaygroupingfuture;
        $this->displaygroupingpast = $config->displaygroupingpast;
        $this->displaygroupingfavourites = $config->displaygroupingfavourites;
        $this->displaygroupinghidden = $config->displaygroupinghidden;
        $this->displaygroupingcustomfield = ($config->displaygroupingcustomfield && $config->customfiltergrouping);
        $this->customfiltergrouping = $config->customfiltergrouping;

        // Check and remember if the grouping selector should be shown at all or not.
        // It will be shown if more than 1 grouping option is enabled.
        $displaygroupingselectors = array($this->displaygroupingallincludinghidden,
                $this->displaygroupingall,
                $this->displaygroupinginprogress,
                $this->displaygroupingfuture,
                $this->displaygroupingpast,
                $this->displaygroupingfavourites,
                $this->displaygroupinghidden);
        $displaygroupingselectorscount = count(array_filter($displaygroupingselectors));
        if ($displaygroupingselectorscount > 1 || $this->displaygroupingcustomfield) { // TIM changed if > 1 to if > 0
            $this->displaygroupingselector = true;
        } else {
            $this->displaygroupingselector = false;
        }
        if (!isloggedin()) $this->displaygroupingselector = false;
        unset ($displaygroupingselectors, $displaygroupingselectorscount);
    }
    /**
     * Determine the most sensible fallback grouping to use (in cases where the stored selection
     * is no longer available).
     * @param object $config
     * @return string
     */
    private function get_fallback_grouping($config) {
        if ($config->displaygroupingall == true) {
            return BLOCK_CATALOGUE_GROUPING_ALL;
        }
        if ($config->displaygroupingallincludinghidden == true) {
            return BLOCK_CATALOGUE_GROUPING_ALLINCLUDINGHIDDEN;
        }
        if ($config->displaygroupinginprogress == true) {
            return BLOCK_CATALOGUE_GROUPING_INPROGRESS;
        }
        if ($config->displaygroupingfuture == true) {
            return BLOCK_CATALOGUE_GROUPING_FUTURE;
        }
        if ($config->displaygroupingpast == true) {
            return BLOCK_CATALOGUE_GROUPING_PAST;
        }
        if ($config->displaygroupingfavourites == true) {
            return BLOCK_CATALOGUE_GROUPING_FAVOURITES;
        }
        if ($config->displaygroupinghidden == true) {
            return BLOCK_CATALOGUE_GROUPING_HIDDEN;
        }
        if ($config->displaygroupingcustomfield == true) {
            return BLOCK_CATALOGUE_GROUPING_CUSTOMFIELD;
        }
        // In this case, no grouping option is enabled and the grouping is not needed at all.
        // But it's better not to leave $this->grouping unset for any unexpected case.
        return BLOCK_CATALOGUE_GROUPING_ALLINCLUDINGHIDDEN;
    }

    /**
     * Set the available layouts based on the config table settings,
     * if none are available, defaults to the cards view.
     *
     * @throws \dml_exception
     *
     */
    public function set_available_layouts() {

        if ($config = get_config('block_catalogue', 'layouts')) {
            $this->layouts = explode(',', $config);
        } else {
            $this->layouts = array(BLOCK_CATALOGUE_VIEW_CARD);
        }
    }

    /**
     * Get the user preferences as an array to figure out what has been selected.
     *
     * @return array $preferences Array with the pref as key and value set to true
     */
    public function get_preferences_as_booleans() {
        $preferences = [];
        $preferences[$this->sort] = true;
        $preferences[$this->grouping] = true;
        // Only use the user view/display preference if it is in available layouts.
        if (in_array($this->view, $this->layouts)) {
            $preferences[$this->view] = true;
        } else {
            $preferences[reset($this->layouts)] = true;
        }

        return $preferences;
    }

    /**
     * Format a layout into an object for export as a Context variable to template.
     *
     * @param string $layoutname
     *
     * @return \stdClass $layout an object representation of a layout
     * @throws \coding_exception
     */
    public function format_layout_for_export($layoutname) {
        $layout = new stdClass();

        $layout->id = $layoutname;
        $layout->name = get_string($layoutname, 'block_catalogue');
        $layout->active = $this->view == $layoutname ? true : false;
        $layout->arialabel = get_string('aria:' . $layoutname, 'block_catalogue');

        return $layout;
    }

    /**
     * Get the available layouts formatted for export.
     *
     * @return array an array of objects representing available layouts
     */
    public function get_formatted_available_layouts_for_export() {

        return array_map(array($this, 'format_layout_for_export'), $this->layouts);

    }

    /**
     * Get the list of values to add to the grouping dropdown
     * @return object[] containing name, value and active fields
     */
    public function get_customfield_values_for_export() {
        global $DB, $USER;
        if (!$this->displaygroupingcustomfield) {
            return [];
        }

        // Get the relevant customfield ID within the core_course/course component/area.
        $fieldid = $DB->get_field_sql("
            SELECT f.id
              FROM {customfield_field} f
              JOIN {customfield_category} c ON c.id = f.categoryid
             WHERE f.shortname = :shortname AND c.component = 'core_course' AND c.area = 'course'
        ", ['shortname' => $this->customfiltergrouping]);
        if (!$fieldid) {
            return [];
        }
        $extraSql = "";
        $params = [];
        if (!is_siteadmin()) {
          $extraSql .= " AND f.shortname = 'published' AND d.intvalue > 0";
        }
        if (!is_null($this->currentcategory)) {
          $extraSql .= " AND c.category = ?";
          $params[] = $this->currentcategory;
        }
        $courses = $DB->get_records_sql("
            SELECT c.* FROM {course} c
            JOIN {customfield_data} d ON d.instanceid = c.id
            JOIN {customfield_field} f ON f.id = d.fieldid
            WHERE c.visible = 1
            $extraSql
        ", $params);
        if (!$courses) {
            return [];
        }
        list($csql, $params) = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
        $select = "instanceid $csql AND fieldid = :fieldid";
        $params['fieldid'] = $fieldid;
        $distinctablevalue = $DB->sql_compare_text('value');
        $values = $DB->get_records_select_menu('customfield_data', $select, $params, '',
            "DISTINCT $distinctablevalue, $distinctablevalue AS value2");
        \core_collator::asort($values, \core_collator::SORT_NATURAL);
        $values = array_filter($values);
        if (!$values) {
            return [];
        }
        $field = \core_customfield\field_controller::create($fieldid);
        $isvisible = $field->get_configdata_property('visibility') == \core_course\customfield\course_handler::VISIBLETOALL;
        // Only visible fields to everybody supporting course grouping will be displayed.
        if (!$field->supports_course_grouping() || !$isvisible) {
            return []; // The field shouldn't have been selectable in the global settings, but just skip it now.
        }
        $values = $field->course_grouping_format_values($values);
        $customfieldactive = ($this->grouping === BLOCK_CATALOGUE_GROUPING_CUSTOMFIELD);
        $ret = [];
        foreach ($values as $value => $name) {
            $ret[] = (object)[
                'name' => $name,
                'value' => $value,
                'active' => ($customfieldactive && ($this->customfieldvalue == $value)),
            ];
        }
        return $ret;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array Context variables for the template
     * @throws \coding_exception
     *
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $DB;

        $nocoursesurl = $output->image_url('courses', 'block_catalogue')->out();

        $newcourseurl = '';
        $coursecat = \core_course_category::user_top();
        if ($coursecat && ($category = \core_course_category::get_nearest_editable_subcategory($coursecat, ['create']))) {
            $newcourseurl = new \moodle_url('/course/edit.php', ['category' => $category->id]);
        }

        $customfieldvalues = $this->get_customfield_values_for_export();
        $selectedcustomfield = '';
        if ($this->grouping == BLOCK_CATALOGUE_GROUPING_CUSTOMFIELD) {
            foreach ($customfieldvalues as $field) {
                if ($field->value == $this->customfieldvalue) {
                    $selectedcustomfield = $field->name;
                    break;
                }
            }
            // If the selected custom field value has not been found (possibly because the field has
            // been changed in the settings) find a suitable fallback.
            if (!$selectedcustomfield) {
                $this->grouping = $this->get_fallback_grouping(get_config('block_catalogue'));
                if ($this->grouping == BLOCK_CATALOGUE_GROUPING_CUSTOMFIELD) {
                    // If the fallback grouping is still customfield, then select the first field.
                    $firstfield = reset($customfieldvalues);
                    if ($firstfield) {
                        $selectedcustomfield = $firstfield->name;
                        $this->customfieldvalue = $firstfield->value;
                    }
                }
            }
        }
        $preferences = $this->get_preferences_as_booleans();
        $availablelayouts = $this->get_formatted_available_layouts_for_export();
        $sort = '';
        if ($this->sort == BLOCK_CATALOGUE_SORTING_SHORTNAME) {
            $sort = 'shortname';
        } else if ($this->sort == BLOCK_CATALOGUE_SORTING_PUBLISHED) {
            $sort = 'startdate desc';
        } else {
            $sort = $this->sort == BLOCK_CATALOGUE_SORTING_TITLE ? 'fullname' : 'ul.timeaccess desc';
        }

        $extraSql = "";
        $params = [];
        if (!is_siteadmin()) {
          $extraSql .= " AND f.shortname = 'published' AND d.intvalue > 0";
        }
        if (!is_null($this->currentcategory)) {
          $extraSql .= " AND c.category = ?";
          $params[] = $this->currentcategory;

        }
        $totalcoursecount = $DB->count_records_sql("
            SELECT count(distinct c.id) FROM {course} c
            JOIN {customfield_data} d ON d.instanceid = c.id
            JOIN {customfield_field} f ON f.id = d.fieldid
            WHERE c.visible = 1
            $extraSql
        ", $params);

        $defaultvariables = [
            'totalcoursecount' => $totalcoursecount,
            'nocoursesimg' => $nocoursesurl,
            'newcourseurl' => $newcourseurl,
            'grouping' => $this->grouping,
            'sort' => $sort,
            // If the user preference display option is not available, default to first available layout.
            'view' => in_array($this->view, $this->layouts) ? $this->view : reset($this->layouts),
            'paging' => $this->paging,
            'layouts' => $availablelayouts,
            'displaycategories' => $this->displaycategories,
            'displaydropdown' => (count($availablelayouts) > 1) ? true : false,
            'displaygroupingallincludinghidden' => $this->displaygroupingallincludinghidden,
            'displaygroupingall' => $this->displaygroupingall,
            'displaygroupinginprogress' => $this->displaygroupinginprogress,
            'displaygroupingfuture' => $this->displaygroupingfuture,
            'displaygroupingpast' => $this->displaygroupingpast,
            'displaygroupingfavourites' => $this->displaygroupingfavourites,
            'displaygroupinghidden' => $this->displaygroupinghidden,
            'displaygroupingselector' => $this->displaygroupingselector,
            'displaygroupingcustomfield' => $this->displaygroupingcustomfield && $customfieldvalues,
            'customfieldname' => $this->customfiltergrouping,
            'customfieldvalue' => $this->customfieldvalue,
            'customfieldvalues' => $customfieldvalues,
            'selectedcustomfield' => $selectedcustomfield,
            'showsortbyshortname' => $CFG->courselistshortnames,
            'showsortbycategory' => $this->showsortbycategories,
            'admin' => is_siteadmin(),
            'isloggedin' => isloggedin()
        ];

        // other things we want to expose to the template
        $parent = $this->topcategoryid;
        $records = $DB->get_records_sql("
                    select id, name from {course_categories} where parent = ?
                    and id in (select category from {course} where visible = 1)
                    order by sortorder, name
                  ", [$parent]);
        foreach ($records as $record) {
          $categories[] = [
            'name'=>$record->name,
            'id'=>$record->id,
            'current'=>($record->id==$this->currentcategory),
            'categorycss'=>strtolower(preg_replace('/[^a-zA-Z\-]/','',str_replace(' ','-', $record->name)))
          ];
        }
        $extras = [
          'categories' => $categories,
          'selectedcategory' => $this->currentcategory
        ];


        return array_merge($defaultvariables, $preferences, $extras);

    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array Context variables for the template
     * @throws \coding_exception
     *
     */
    public function export_for_zero_state_template(renderer_base $output) {
        global $CFG, $DB;

        $nocoursesimg = $output->image_url('courses', 'block_catalogue');

        $buttons = [];
        $coursecat = \core_course_category::user_top();
        if ($coursecat) {
            // Request a course button.
            $category = \core_course_category::get_nearest_editable_subcategory($coursecat, ['moodle/course:request']);
            if ($category && $category->can_request_course()) {
                $requestbutton = new \single_button(
                    new \moodle_url('/course/request.php', ['category' => $category->id]),
                    get_string('requestcourse'),
                    'post',
                    \single_button::BUTTON_PRIMARY
                );
                $buttons[] = $requestbutton->export_for_template($output);
                return $this->generate_zero_state_data(
                    $nocoursesimg,
                    $buttons,
                    [
                        'title' => 'zero_request_title',
                        'intro' => ($CFG->coursecreationguide ? 'zero_request_intro' : 'zero_nocourses_intro'),
                    ],
                );
            }

            $totalcourses = $DB->count_records_select('course', 'category > 0');
            if ($coursecat) {
                // Manage courses or categories button.
                $managebuttonname = get_string('managecategories');
                if ($totalcourses) {
                    $managebuttonname = get_string('managecourses');
                }
                if ($categorytomanage = \core_course_category::get_nearest_editable_subcategory($coursecat, ['manage'])) {
                    $managebutton = new \single_button(
                        new \moodle_url('/course/management.php', ['category' => $categorytomanage->id]),
                        $managebuttonname,
                    );
                    $buttons[] = $managebutton->export_for_template($output);
                }
            }

            // Create course button.
            if ($category = \core_course_category::get_nearest_editable_subcategory($coursecat, ['create'])) {
                $createbutton = new \single_button(
                    new \moodle_url('/course/edit.php', ['category' => $category->id]),
                    get_string('createcourse', 'block_catalogue'),
                    'post',
                    \single_button::BUTTON_PRIMARY,
                );
                $buttons[] = $createbutton->export_for_template($output);

                $title = $totalcourses ? 'zero_default_title' : 'zero_nocourses_title';
                $intro = $totalcourses ? 'zero_default_intro' :
                        ($CFG->coursecreationguide ? 'zero_request_intro' : 'zero_nocourses_intro');
                return $this->generate_zero_state_data(
                    $nocoursesimg,
                    $buttons,
                    ['title' => $title, 'intro' => $intro],
                );
            }

        }

        return $this->generate_zero_state_data(
            $nocoursesimg,
            $buttons,
            ['title' => 'zero_default_title', 'intro' => 'zero_default_intro']
        );
    }

    /**
     * Generate the state zero data.
     *
     * @param \moodle_url $imageurl The URL to the image to show
     * @param string[] $buttons Exported {@see \single_button} instances
     * @param array $strings Title and intro strings for the zero state if needed.
     * @return array Context variables for the template
     */
    private function generate_zero_state_data(\moodle_url $imageurl, array $buttons, array $strings) {
        global $CFG;
        // Documentation data.
        $dochref = new \moodle_url($CFG->docroot, ['lang' => current_language()]);
        $docparams = [
            'dochref' => $dochref->out(),
            'doctitle' => get_string('documentation'),
            'doctarget' => $CFG->doctonewwindow ? '_blank' : '_self',
        ];
        if ($CFG->coursecreationguide) {
            // Add quickstart guide link.
            $quickstart = new \moodle_url($CFG->coursecreationguide, ['lang' => current_language()]);
            $docparams += [
                'quickhref' => $quickstart->out(),
                'quicktitle' => get_string('viewquickstart', 'block_catalogue'),
                'quicktarget' => '_blank',
            ];
        }
        return [
            'nocoursesimg' => $imageurl->out(),
            'title' => ($strings['title']) ? get_string($strings['title'], 'block_catalogue') : '',
            'intro' => ($strings['intro']) ? get_string($strings['intro'], 'block_catalogue', $docparams) : '',
            'buttons' => $buttons,
        ];
    }
}
