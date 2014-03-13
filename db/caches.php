<?php
/**
 * Our MUC caches
 */
$definitions = array(
    'kent_course_overview' => array(
        'mode' => cache_store::MODE_SESSION
    ),
    'kent_course_overview_reset' => array(
        'mode' => cache_store::MODE_APPLICATION
    )
);