<?php
/* 
 * Overriding the god awful print overview function in lib
 */
function kent_course_print_overview($courses, $baseurl, array $remote_courses=array()) {
    global $CFG, $USER, $DB, $OUTPUT;

    $content = '';
    $extra_class_attributes = '';

    foreach ($courses as $course) {
        $rollover = new \local_rollover\Course($course->id);

        $context = context_course::instance($course->id);
        $fullname = format_string($course->fullname, true, array('context' => $context));
        $shortname = format_string($course->shortname, true, array('context' => $context));
        
        if (empty($course->visible)) {
            $extra_class_attributes = ' dimmed';
        }

        $attributes = array('title' => s($fullname), 'class' => 'course_list');

        $perms_to_rollover = has_capability('moodle/course:update', $context);

        //Ensure Rollover is installed before we do anything and that the course doesn't have content.
        $rollover_installed = \local_kent\util\sharedb::available();

        $list_class = '';

        $admin_hide = 'admin_hide';

        $width = '';

        if($rollover_installed){
            $rollover_status = $rollover->get_status();
            $rolloverable = $rollover->can_rollover();

            if ($rolloverable) {
                if ($perms_to_rollover){
                    $width = 'admin_width';
                    $admin_hide = '';
                    $list_class = 'rollover_'.$rollover_status.' ';
                }
            }
        }

        //Construct link
        $content .= '<li class="'.$list_class.(!$course->visible ? 'course_unavailable' : '').'">';
        if (has_capability('moodle/course:visibility', $context) && has_capability('moodle/course:viewhiddencourses', $context)) {
            // if user can view hidden and can adjust visibility, we'll let them change it from here
            if (!empty($course->visible)) {
                $url = new moodle_url($baseurl, array('hide' => $course->id));
                $img = $OUTPUT->action_icon($url, new pix_icon('t/hide', get_string('hide')));
            } else {
                $url = new moodle_url($baseurl, array('show' => $course->id));
                $img = $OUTPUT->action_icon($url, new pix_icon('t/show', get_string('show')));
            }
            $content .= "<div class='visibility_tri'></div><div class='course_adjust_visibility'>" . $img . "</div>";
        }
        $content .= '<div class="course_details_ovrv '.$width. '" >';
        
        $name = $fullname;
        if(isset($CFG->courselistshortnames)) {
            if($CFG->courselistshortnames === '1') {
                $name = $shortname . ': ' . $fullname;
            }
        }

        $content .= '<span class="title">'.html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)), $name, $attributes) . '</span>';

        if(isset($course->summary) && $course->summary != ""){
            $content .= ' <span class="course_description">'.$course->summary.'</span>';
        }


        $content .= kent_add_teachers($course, $context);
        
        $content .= '</div>';

        //If user has ability to update the course and the course is empty to signify a rollover
        if($rollover_installed && $perms_to_rollover){

            $clearmodule = get_config('block_kent_course_overview', 'clearmodule');

            $rollover_path = $CFG->wwwroot.'/local/rollover/index.php?srch='.$course->shortname;

            if ($rollover_status == \local_rollover\Rollover::STATUS_NONE && !($rolloverable) && $clearmodule) {
                $admin_hide = '';
            }

            $content .= ' <div class="course_admin_options '.$admin_hide.'">';
            switch ($rollover_status) {
                case \local_rollover\Rollover::STATUS_NONE:
                    if($rolloverable){
                        $content .= '<a class="course_rollover_optns new" href="'.$rollover_path.'">Empty module. <br / > Click here to <br /><strong>Rollover module</strong></a>';
                    } elseif($clearmodule) {
                        $admin_hide = '';
                        $content .= '<a class="course_clear_optns new" href="#'.$course->id.'">'.get_string('clearmodulebutton', 'block_kent_course_overview').'</a>'; 
                    }
                    break;
                case \local_rollover\Rollover::STATUS_COMPLETE:
                    if($clearmodule){
                        $admin_hide = '';
                        $content .= '<a class="course_clear_optns new" href="#'.$course->id.'">'.get_string('clearmodulebutton', 'block_kent_course_overview').'</a>';
                    }
                    break;
                case \local_rollover\Rollover::STATUS_SCHEDULED:
                    $content .= '<div class="course_rollover_optns pending">Rollover pending</div>';
                    break;
                case \local_rollover\Rollover::STATUS_BACKED_UP:
                case \local_rollover\Rollover::STATUS_IN_PROGRESS:
                    $content .= '<div class="course_rollover_optns pending">Rollover in process</div>';
                    break;
                default:
                    if ($clearmodule) {
                        $admin_hide = '';
                        $content .= '<a class="course_clear_optns new" href="#'.$course->id.'">'.get_string('clearmodulebutton', 'block_kent_course_overview').'</a>';
                    }
            }


            $content .= '</div><div style="clear: both"></div>';
        }

        $content .= '</li>';

    }

    if($content != '') {
        $content = '<ul id="kent_course_list_overview">'.$content.'</ul>';
    }

    return $content;

}


/*
 * Function to pull in teachers linked on a course
 */
function kent_add_teachers($course, $context){

    global $CFG, $DB;

    $string = '';

    /// first find all roles that are supposed to be displayed
    if (!empty($CFG->coursecontact)) {
        $managerroles = explode(',', $CFG->coursecontact);
        $namesarray = array();
        $rusers = array();

        $roles_limit = (int)get_string('roles_limit', 'block_kent_course_overview');
        $roles_string = get_string('ignore_roles', 'block_kent_course_overview');

        $ignore_role_ids = explode(",", $roles_string);
        
        //prepare roles sql.
        $roles_sql = '';
        if(!empty($ignore_role_ids) && $ignore_role_ids[0] != ''){

            foreach($ignore_role_ids as $roles){
                $roles_sql .= $roles . ',';
            }

            $roles_sql = substr($roles_sql, 0, -1);
            $roles_sql = 'ra.roleid NOT IN (' . $roles_sql . ')';

        }

        $userfields = get_all_user_name_fields(true, 'u');
        $rusers = get_role_users($managerroles, $context, true,
            'ra.id AS raid, u.id, u.username, '.$userfields.',
             r.name AS rolename, r.sortorder, r.id AS roleid',
            'r.sortorder ASC, u.lastname ASC',
            'u.lastname, u.firstname', '', '', $roles_limit, $roles_sql);


        /// Rename some of the role names if needed
        if (isset($context)) {
            $aliasnames = $DB->get_records('role_names', array('contextid'=>$context->id), '', 'roleid,contextid,name');
        }

        $namesarray = array();
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $context);
        foreach ($rusers as $ra) {
            if (isset($namesarray[$ra->id])) {
                //  only display a user once with the higest sortorder role
                continue;
            }

            if (isset($aliasnames[$ra->roleid])) {
                $ra->rolename = $aliasnames[$ra->roleid]->name;
            }

            $fullname = fullname($ra, $canviewfullnames);
            $namesarray[$ra->id] = format_string($ra->rolename).': '.
                html_writer::link(new moodle_url('/user/view.php', array('id'=>$ra->id, 'course'=>SITEID)), $fullname);
        }


        if (!empty($namesarray)) {

            $string .= '<div class="teachers_show_hide">'.get_string('staff_toggle', 'block_kent_course_overview').'</div>';
            $string .= html_writer::start_tag('div', array('class'=>'teachers'));
            foreach ($namesarray as $name) {
                $string .= html_writer::tag('span', $name);
            }
            $string .= html_writer::end_tag('div');
        }
    }

    return $string;
}

/**
 * Returns list of courses current $USER is enrolled in and can access
 *
 * - $fields is an array of field names to ADD
 *   so name the fields you really need, which will
 *   be added and uniq'd
 *
 * @param string|array $fields
 * @param string $sort
 * @param int $limit max number of courses
 * @return array
 */
function kent_enrol_get_my_courses($fields = NULL, $sort = 'sortorder ASC', $page, $perpage) {
    global $USER;

    $courses = enrol_get_users_courses($USER->id, false, $fields, $sort);
    $courseset = array_slice($courses, $page, $perpage, true);
    return array('totalcourses' => count($courses), 'courses' => $courseset);
}

function kent_enrol_get_my_categories($fields = NULL, $sort = 'sortorder ASC') {
    global $DB, $USER;

    // Guest account does not have any courses
    if (isguestuser() or !isloggedin()) {
        return(array());
    }

    $basefields = array('cc.id', 'cc.name', 'cc.sortorder');

    if (empty($fields)) {
        $fields = $basefields;
    } else if (is_string($fields)) {
        // turn the fields from a string to an array
        $fields = explode(',', $fields);
        $fields = array_map('trim', $fields);
        $fields = array_unique(array_merge($basefields, $fields));
    } else if (is_array($fields)) {
        $fields = array_unique(array_merge($basefields, $fields));
    } else {
        throw new coding_exception('Invalid $fields parameter in kent_enrol_get_my_categories()');
    }
    if (in_array('*', $fields)) {
        $fields = array('*');
    }

    $orderby = "";
    $sort    = trim($sort);
    if (!empty($sort)) {
        $rawsorts = explode(',', $sort);
        $sorts = array();
        foreach ($rawsorts as $rawsort) {
            $rawsort = trim($rawsort);
            if (strpos($rawsort, 'c.') === 0) {
                $rawsort = substr($rawsort, 2);
            }
            $sorts[] = trim($rawsort);
        }
        $sort = 'c.'.implode(',c.', $sorts);
        $orderby = "ORDER BY $sort";
    }

    $wheres = array("u.id = :userid");

    $coursefields = 'c.' .join(',c.', $fields);

    $wheres = implode(" AND ", $wheres);

    //note: we can not use DISTINCT + text fields due to Oracle and MS limitations, that is why we have the subselect there

        /*
        SELECT cc.name, cc.id, u.*
        from mdl_course_categories cc
        JOIN mdl_context c
        ON cc.id=c.instanceid
        AND c.contextlevel=40 -- Magic number
        JOIN mdl_role_assignments ra
        ON ra.contextid=c.id
        JOIN mdl_user u
        ON ra.userid=u.id
        */


    $sql = "SELECT cc.id, cc.name, cc.sortorder
            FROM {course_categories} cc
            INNER JOIN {context} c
                ON cc.id=c.instanceid
                    AND c.contextlevel=40 -- Magic number
            INNER JOIN {role_assignments} ra
                ON ra.contextid=c.id
            INNER JOIN {user} u
                ON ra.userid=u.id
            WHERE $wheres
            $orderby
            GROUP BY cc.id";
    $params['userid']  = $USER->id;

    //$totalcategories = count($DB->get_records_sql($sql, $params));
    //$categories = $DB->get_records_sql($sql, $params, $page, $perpage);
    $categories = $DB->get_records_sql($sql, $params);

    $totalcategories = count($categories);

    //echo var_dump(array('totalcategories' => $totalcategories, 'categories' => $categories));

    return array('totalcategories' => $totalcategories, 'categories' => $categories);
}


function kent_category_print_overview($categories, $baseurl) {
    global $CFG, $USER, $DB, $OUTPUT;

    $content = '';
    $extra_class_attributes = '';

    foreach ($categories as $category) {
        $width='';
        $attributes = array('title' => s($category->name), 'class' => 'course_list');

        //Construct link
        $content .= '<li class="course">';
        $content .= '<div class="course_details_ovrv '.$width. '" >';
        
        $content .= '<span class="title">'.html_writer::link(new moodle_url('/course/category.php', array('id' => $category->id)), $category->name, $attributes) . '</span>';

        $content .= '</div><div style="clear: both"></div>';

        $content .= '</li>';

    }

    if($content != ''){
        $content = '<ul id="kent_category_list_overview">'.$content.'</ul>';
    }

    return $content;

}
