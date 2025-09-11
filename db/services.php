<?php


defined('MOODLE_INTERNAL') || die();

$functions = [

    'block_catalogue_get_filtered_courses' => array(
        'classpath' => 'block/catalogue/classes/external.php',
        'classname'   => 'block_catalogue_external',
        'methodname'  => 'get_filtered_courses',
        'description' => 'Get all courses in catalogue.',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => false
    )
];
