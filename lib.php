<?php
/* 
 * Overriding the god awful print overview function in lib
 */
function kent_course_print_overview($courses, $baseurl, array $remote_courses=array()) {
    global $CFG, $USER, $DB, $OUTPUT;

    $content = '';
    $extra_class_attributes = '';

    foreach ($courses as $course) {
        $fullname = format_string($course->fullname, true, array('context' => get_context_instance(CONTEXT_COURSE, $course->id)));
        $shortname = format_string($course->shortname, true, array('context' => get_context_instance(CONTEXT_COURSE, $course->id)));
        
        if (empty($course->visible)) {
            $extra_class_attributes = ' dimmed';
        }

        $attributes = array('title' => s($fullname), 'class' => 'course_list');

        $context = get_context_instance(CONTEXT_COURSE, $course->id);
        $perms_to_rollover = has_capability('moodle/course:update', $context);

        //Ensure Rollover is installed before we do anything and that the course doesn't have content.
        $rollover_installed = $DB->get_records('config_plugins', array('plugin'=>'local_rollover'), '', 'plugin');

        $list_class = '';

        $admin_hide = 'admin_hide';

        $width = '';

        if($rollover_installed){
            //Fetch the rollover lib to leverage some functions
            require_once($CFG->dirroot.'/local/rollover/lib.php');
            $rollover_status = kent_get_current_rollover_status($course->id);

            if($rolloverable = kent_rollover_ability($course->id, $rollover_status)){
                if ($perms_to_rollover){
                    $width = 'admin_width';
                    $admin_hide = '';
                    $list_class = 'rollover_'.$rollover_status.' ';
                }
            }
        }

        //Construct link
        $content .= '<li class="'.$list_class.(!$course->visible ? 'course_unavailable' : '').'">';
        if ($course->user_can_adjust_visibility && $course->user_can_view) {
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

            $rollover_path = $CFG->wwwroot.'/local/rollover/index.php#rollover_form_'.$course->id;

            $content .= ' <div class="course_admin_options '.$admin_hide.'">';

            switch ($rollover_status) {
                case 'none':
                    if($rolloverable){
                        $content .= '<a class="course_rollover_optns new" href="'.$rollover_path.'">Empty module. <br / > Click here to <br /><strong>Rollover module</strong></a>';
                    }
                    break;
                case 'complete':
                    $content .= '';
                    break;
                case 'requested':
                    $content .= '<div class="course_rollover_optns pending">Rollover pending</div>';
                    break;
                case 'processing':
                    
                    $content .= '<div class="course_rollover_optns pending">Rollover in process</div>';
                    break;
                default:
                    $content .= '<a class="course_rollover_optns error" href="'.$rollover_path.'">Previous rollover <br /> failed <br /><strong> - please contact an admin.</strong></a>';
            }


            $content .= '</div><div style="clear: both"></div>';
        }

        $content .= '</li>';

    }

    if($content != ''){
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

        $rusers = get_role_users($managerroles, $context, true,
            'ra.id AS raid, u.id, u.username, u.firstname, u.lastname,
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


/*
 * Add in a Kent Archive moodle link.
 */
function kent_archive_moodle_link(){
    global $CFG;
    
    $output = '';

    if(isset($CFG->archive_moodle) && ($CFG->archive_moodle == TRUE)){

        $archive_path = 'https://moodle.kent.ac.uk'; //Initially set a default path
        if(isset($CFG->archive_moodle_path) || $CFG->archive_moodle_path != ''){
            $archive_path = $CFG->archive_moodle_path;
        }

        //Determine link text
        $archive_link_text = 'Other moodle modules';

        if(!kent_is_archive_moodle()){
            $archive_link_text = ((isset($CFG->archive_old_moodle_link_text) && ($CFG->archive_old_moodle_link_text != '')) ? $CFG->archive_old_moodle_link_text : $archive_link_text);

            //Check if there is any overriding language file modifications to the link text
            $lang_link_text = get_string('archive_old_moodle_link_text', 'block_kent_course_overview');
            if($lang_link_text != '[[archive_old_moodle_link_text]]' && $lang_link_text != ''){
                $archive_link_text = $lang_link_text;
            }
            
        } else {
            $archive_link_text = ((isset($CFG->archive_current_moodle_link_text) && ($CFG->archive_current_moodle_link_text != '')) ? $CFG->archive_current_moodle_link_text : $archive_link_text);

            //Check if there is any overriding language file modifications to the link text
            $lang_link_text = get_string('archive_current_moodle_link_text', 'block_kent_course_overview');
            if($lang_link_text != '[[archive_current_moodle_link_text]]' && $lang_link_text != ''){
                $archive_link_text = $lang_link_text;
            }

        }

        $archive_link = '<div class="archive_link"><a href="'.$archive_path.'">'.$archive_link_text.'</a></div>';

        $output = $archive_link;

    }

    return $output;

}


/*
 * Helper function to determine if this is archive moodle.
 */
function kent_is_archive_moodle(){
    global $CFG;
    
    if (isset($CFG->archive_moodle_this_is_archive) && ($CFG->archive_moodle_this_is_archive == TRUE)){
        return TRUE;
    }
    return FALSE;
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
    global $DB, $USER;

    // Guest account does not have any courses
    if (isguestuser() or !isloggedin()) {
        return(array());
    }

    $basefields = array('id', 'category', 'sortorder',
                        'shortname', 'fullname', 'idnumber',
                        'startdate', 'visible',
                        'groupmode', 'groupmodeforce');

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
        throw new coding_exception('Invalid $fields parameter in enrol_get_my_courses()');
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

    $wheres = array("c.id <> :siteid");
    $params = array('siteid'=>SITEID);

    if (isset($USER->loginascontext) and $USER->loginascontext->contextlevel == CONTEXT_COURSE) {
        // list _only_ this course - anything else is asking for trouble...
        $wheres[] = "courseid = :loginas";
        $params['loginas'] = $USER->loginascontext->instanceid;
    }

    $coursefields = 'c.' .join(',c.', $fields);
    list($ccselect, $ccjoin) = context_instance_preload_sql('c.id', CONTEXT_COURSE, 'ctx');
    $wheres = implode(" AND ", $wheres);

    //note: we can not use DISTINCT + text fields due to Oracle and MS limitations, that is why we have the subselect there

    $sql = "SELECT $coursefields $ccselect
              FROM {course} c
              JOIN (SELECT DISTINCT e.courseid
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = :userid)
                     WHERE ue.status = :active AND e.status = :enabled AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)
                   ) en ON (en.courseid = c.id)
           $ccjoin
             WHERE $wheres
          $orderby";
    $params['userid']  = $USER->id;
    $params['active']  = ENROL_USER_ACTIVE;
    $params['enabled'] = ENROL_INSTANCE_ENABLED;
    $params['now1']    = round(time(), -2); // improves db caching
    $params['now2']    = $params['now1'];

    //$totalcourses = count($DB->get_records_sql($sql, $params));
    //$courses = $DB->get_records_sql($sql, $params, $page, $perpage);
    $courses = $DB->get_records_sql($sql, $params);

    $totalcourses = count($courses);
    $courseset = array_slice($courses, $page, $perpage, true);

    // preload contexts and check visibility
    foreach ($courseset as $id=>$course) {
        context_instance_preload($course);
        /*if (!$course->visible) {
            if (!$context = get_context_instance(CONTEXT_COURSE, $id)) {
                unset($courseset[$id]);
                continue;
            }
            if (!has_capability('moodle/course:viewhiddencourses', $context)) {
                unset($courseset[$id]);
                continue;
            }
        }*/
        if ($context = get_context_instance(CONTEXT_COURSE, $id)) {
            if (has_capability('moodle/course:viewhiddencourses', $context)) {
                $course->user_can_view = true;
            } else {
                $course->user_can_view = false;
            }
            if (has_capability('moodle/course:visibility', $context)) {
                $course->user_can_adjust_visibility = true;
            } else {
                $course->user_can_adjust_visibility = false;
            }
        }
        $courseset[$id] = $course;
    }

    return array('totalcourses' => $totalcourses, 'courses' => $courseset);
}