<?php
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/externallib.php');

use block_catalogue\external\course_summary_exporter; // modded clone of core_course\external\course_summary_exporter
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use \core_tag_tag;

class block_catalogue_external extends core_course_external {

   /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.6
     */
    public static function get_filtered_courses_parameters() {
        return new external_function_parameters(
            array(
                'classification' => new external_value(PARAM_ALPHA, 'future, inprogress, or past'),
                'limit' => new external_value(PARAM_INT, 'Result set limit', VALUE_DEFAULT, 0),
                'offset' => new external_value(PARAM_INT, 'Result set offset', VALUE_DEFAULT, 0),
                'sort' => new external_value(PARAM_TEXT, 'Sort string', VALUE_DEFAULT, null),
                'customfieldname' => new external_value(PARAM_ALPHANUMEXT, 'Used when classification = customfield',
                    VALUE_DEFAULT, null),
                'customfieldvalue' => new external_value(PARAM_RAW, 'Used when classification = customfield',
                    VALUE_DEFAULT, null),
                'searchvalue' => new external_value(PARAM_RAW, 'The value a user wishes to search against',
                    VALUE_DEFAULT, null),
                'requiredfields' => new core_external\external_multiple_structure(
                    new external_value(PARAM_ALPHANUMEXT, 'Field name to be included from the results', VALUE_DEFAULT),
                    'Array of the only field names that need to be returned. If empty, all fields will be returned.',
                    VALUE_DEFAULT, []
                ),
            )
        );

    }

    /**
     * Get all listed courses.
     *
     * @param int $limit Limit
     * @param int $offset Offset
     *
     * @return  array list of courses and warnings
     */
    public static function get_filtered_courses(
        string $classification,
        int $limit = 0,
        int $offset = 0,
        ?string $sort = null,
        ?string $customfieldname = null,
        ?string $customfieldvalue = null,
        ?string $searchvalue = null,
        array $requiredfields = []
    ) {
        global $CFG, $PAGE, $DB, $USER;
        require_once($CFG->dirroot . '/course/lib.php');

        $admin = is_siteadmin();
        //$courseadmin = has_capability('moodle/site:config', \context_system::instance());

        $params = self::validate_parameters(
            self::get_enrolled_courses_by_timeline_classification_parameters(),
            array(
                'classification' => $classification,
                'limit' => $limit,
                'offset' => $offset,
                'sort' => $sort,
                'customfieldvalue' => $customfieldvalue,
                'searchvalue' => $searchvalue,
                'requiredfields' => $requiredfields,
            )
        );

        $searchcriteria = [];
        $dbquerylimit = COURSE_DB_QUERY_LIMIT;
        $classification = $params['classification'];
        $limit = $params['limit'];
        $offset = $params['offset'];
        $sort = $params['sort'];
        $customfieldvalue = $params['customfieldvalue'];
        $searchvalue = clean_param($params['searchvalue'], PARAM_TEXT);
        $filter_ids = [];
        $requiredfields = $params['requiredfields'];
        $haslimit = !empty($limit);

        switch ($classification) {
            case COURSE_TIMELINE_ALLINCLUDINGHIDDEN:
                break;
            case COURSE_TIMELINE_ALL:
                break;
            case COURSE_TIMELINE_PAST:
                break;
            case COURSE_TIMELINE_INPROGRESS:
                break;
            case COURSE_TIMELINE_FUTURE:
                break;
            case COURSE_FAVOURITES:
                break;
            case COURSE_TIMELINE_HIDDEN:
                break;
            case COURSE_TIMELINE_SEARCH:
                $searchcriteria['search'] = $searchvalue;
                if ($sort !== "lastaccessed") {
                    $querylimit = (!$haslimit || $limit > $dbquerylimit) ? $dbquerylimit : $limit;
                    $filter_ids = core_course_category::search_courses($searchcriteria, [
                        'idonly' => true,
                        'customfields' => true,
                        'limittoenrolled' => false
                    ]);
                }
                break;
            case COURSE_CUSTOMFIELD:
                break;
            default:
                throw new invalid_parameter_exception('Invalid classification ' . $classification);
        }

        $defaultProperties = course_summary_exporter::define_properties();
        $exporterfields = array_keys($defaultProperties);
        
        // Get the required properties from the exporter fields based on the required fields.
        $requiredproperties = array_intersect($exporterfields, $requiredfields);
        // If the resulting required properties is empty, fall back to the exporter fields.
        if (empty($requiredproperties)) {
            $requiredproperties = $exporterfields;
        }

        $filter_category = get_user_preferences('block_catalogue_user_filter_category');

        $showTags = ((get_config('core', 'usetags') == 1) && get_config('block_catalogue', 'showtags') == 1);

        //
        // $fields = 'c.' .join(',c.', $requiredproperties); // join(',', array_map(function($v) { return 'c.'.$v; }, $requiredproperties));

        if ($sort === "categoryname") $sort = "cc.name";
        if ($sort === "ul.timeaccess desc") { // "lastaccessed") {
            $fields = join(',', $requiredproperties);
            if ($classification === COURSE_TIMELINE_SEARCH) {
                $courses = course_get_enrolled_courses_for_logged_in_user_from_search(
                    0,
                    $offset,
                    $sort,
                    $fields,
                    COURSE_DB_QUERY_LIMIT,
                    $searchcriteria,
                    $options
                );
            } else {
                $courses = course_get_enrolled_courses_for_logged_in_user(0, $offset, $sort, $fields, COURSE_DB_QUERY_LIMIT);
            }
        } else {

            $filterSql = "";
            $params = [];
            $orderBy = "ORDER BY " . $sort;
            if (!$admin) {
                $filterSql .= " AND f.shortname = 'listed' AND d.intvalue > 0"; // ?? published is date published .. do we mean 'listed'
            }
            if (!empty($filter_ids)) {
                $filterSql .= " AND c.id IN (" . join(',', $filter_ids) . ")";
            }
            if (!empty($filter_category)) {
                $filterSql .= " AND cc.id=?";
                $params[] = $filter_category;
            }

            $courses = $DB->get_records_sql("
                SELECT DISTINCT c.* FROM {course} c
                JOIN {course_categories} cc ON c.category = cc.id
                JOIN {customfield_data} d ON d.instanceid = c.id
                JOIN {customfield_field} f ON f.id = d.fieldid
                WHERE c.visible = 1
                $filterSql
                $orderBy
                ", $params, $querylimit, $offset);
        }

        $PAGE->set_context(\context_system::instance()); // PAGE needs a context otherwise get_renderer can throw a tanty, particularly in developer mode
        $renderer = $PAGE->get_renderer('core');

        // Get the user favourites service, scoped to a single user (their favourites only).
        $favouritecourseids = [];
        if ($USER->id > 0) {
          // adapted from /blocks/starredcourses/classes/external.php, lime 75
          $usercontext = context_user::instance($USER->id);
          $userservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);
          $favourites = $userservice->find_favourites_by_type('core_course', 'courses', $offset, $limit);
          if ($favourites) {
              $favouritecourseids = array_map(
                  function($favourite) {
                      return $favourite->itemid;
                  }, $favourites);
          }

          if (get_user_preferences('block_catalogue_user_grouping_preference') === 'favourites') {
            $filteredcourses = [];
            foreach ($courses as $course) {
              if (in_array($course->id, $favouritecourseids)) {
                $filteredcourses[] = $course;
              }
            }
            $courses = $filteredcourses;
          }
        }


        $formattedcourses = [];
        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            $canviewhiddencourses = has_capability('moodle/course:viewhiddencourses', $context);
            if ($course->visible || $canviewhiddencourses) {
                $related = [
                  'context' => $context,
                  'isfavourite' => in_array($course->id, $favouritecourseids),
                  'courseadmin' => has_capability('moodle/course:update', $context),
                  'actionmenu' => isloggedin(),
                  'tags' => '',
                ];
                if ($showTags) {
                  $related['tags'] = self::format_tags(\core_tag_tag::get_item_tags_array('core', 'course', $course->id)) . self::other_properties($course);
                }
                $exporter = new course_summary_exporter($course, $related);
                $formattedcourse = $exporter->export($renderer);


                // $formattedcourse->courseadmin = has_capability('moodle/course:update', $context); // yes, if you have edit permission
                // $formattedcourse->actionmenu = isloggedin(); // yes, if you are logged in

                $formattedcourses[] = $formattedcourse;
            }
        }

        return $formattedcourses;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.6
     */
    public static function get_filtered_courses_returns() {
        return new external_multiple_structure(\block_catalogue\external\course_summary_exporter::get_read_structure());
    }

    private static function format_tags($tags = []) {
      $data = '';
      foreach ($tags as $id => $name) {
        $css = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', html_entity_decode($name)));
        $data .= "<span class='tag {$css}'>{$name}</span>"; //  data-id='{$id}' do we care?
      }
      return $data;
    }

    private static function other_properties($course) {
      //  $formats = array('date', 'datefullshort', 'dateshort', 'datetime',
      //          'datetimeshort', 'daydate', 'daydatetime', 'dayshort', 'daytime',
      //          'monthyear', 'recent', 'recentfull', 'time');

      $d = userdate($course->startdate,  get_string('strftimedate','langconfig'));
      $html = "<span class='property startdate'>{$d}</span>";

      return $html;
    }
}
