<?php
/* 
 * Overriding the god awful print overview function in lib
 */
function kent_course_print_overview($courses, array $remote_courses=array()) {
    global $CFG, $USER, $DB, $OUTPUT;

    $content = '';
    $extra_class_attributes = '';

    foreach ($courses as $course) {
        $fullname = format_string($course->fullname, true, array('context' => get_context_instance(CONTEXT_COURSE, $course->id)));
        
        if (empty($course->visible)) {
            $extra_class_attributes = ' dimmed';
        }

        $attributes = array('title' => s($fullname), 'class' => 'course_list'.$extra_class_attributes);

        $context = get_context_instance(CONTEXT_COURSE, $course->id);
        $perms_to_rollover = has_capability('moodle/course:update', $context);
        $course_has_content = kent_course_has_content($course->id);


        $rollover_status = kent_get_current_rollover_status($course->id);

        $list_class = '';
        if($rolloverable = kent_rollover_ability($course->id, $rollover_status)){
            if ($perms_to_rollover){
                $list_class = ' class="rollover_'.$rollover_status.'"';
            }
        }

        //Construct link
        $content .= '<li'.$list_class.'>';
        $content .= '<div class="course_details_ovrv">';
        $content .= '<span class="title">'.html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)), $fullname, $attributes) . '</span>';

        if(isset($course->summary) && $course->summary != ""){
            $content .= ' <span class="course_description">'.$course->summary.'</span>';
        }


        $content .= kent_add_teachers($course, $context);
        
        $content .= '</div>';

        //If user has ability to update the course and the course is empty to signify a rollover
        if ($rolloverable && $perms_to_rollover){

            $rollover_path = $CFG->wwwroot.'/local/rollover/index.php#rollover_form_'.$course->id;

            $content .= ' <div class="course_admin_options">';


            switch ($rollover_status) {
                case 'none':
                    $content .= '<a class="course_rollover_optns new" href="'.$rollover_path.'">Empty course. <br / > Click here to <br /><strong>Rollover course</strong></a>';
                    break;
                case 'complete':
                    $content .= '';
                    break;
                case 'processing':
                    $content .= '<div class="course_rollover_optns pending">Rollover pending</div>';
                    break;
                default:
                    $content .= '<a class="course_rollover_optns error" href="'.$rollover_path.'">Previous rollover <br /> failed <br /><strong> - please contact an admin.</strong></a>';
            }


            $content .= '</div>';
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
            $string .= html_writer::start_tag('span', array('class'=>'teachers'));
            foreach ($namesarray as $name) {
                $string .= html_writer::tag('span', $name);
            }
            $string .= html_writer::end_tag('span');
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
        $archive_link_text = ((!kent_is_archive_moodle() && isset($CFG->archive_old_moodle_link_text) && ($CFG->archive_old_moodle_link_text != '')) ? $CFG->archive_old_moodle_link_text : $archive_link_text);
        $archive_link_text = ((kent_is_archive_moodle() && isset($CFG->archive_current_moodle_link_text) && ($CFG->archive_current_moodle_link_text != '')) ? $CFG->archive_current_moodle_link_text : $archive_link_text);

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