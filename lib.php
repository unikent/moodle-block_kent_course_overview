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
            if ($perms_to_rollover && !$course_has_content){
                $list_class = ' class="rollover_'.$rollover_status.'"';
            }
        }

        //Construct link
        $content .= '<li'.$list_class.'>';
        $content .= '<span class="title">'.html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)), $fullname, $attributes) . '</span>';

        if(isset($course->summary) && $course->summary != ""){
            $content .= ' <span class="course_description">'.$course->summary.'</span>';
        }


        //If user has ability to update the course and the course is empty to signify a rollover
        if ($rolloverable && $perms_to_rollover && !$course_has_content){

            $rollover_path = $CFG->wwwroot.'/local/rollover/index.php#rollover_form_'.$course->id;

            $content .= ' <span class="course_admin_options">';

            $content .= 'Rollover status: ';

            switch ($rollover_status) {
                case 'none':
                    $content .= '<a href="'.$rollover_path.'">Rollover course</a>';
                    break;
                case 'complete':
                    $content .= 'Previous rollover complete - <a href="'.$rollover_path.'">Rollover again</a>';
                    break;
                case 'processing':
                    $content .= 'Rollover in progress';
                    break;
                default:
                    $content .= 'Previous rollover failed - <a href="'.$rollover_path.'">Rollover again</a>';
            }


            $content .= '</span>';
        }

        $content .= '</li>';

    }

    if($content != ''){
        $content = '<ul id="kent_course_list_overview">'.$content.'</ul>';
    }

    return $content;

}


/**
 * Check if a specified course has any content based on modules and summaries
 * @param <int> $course_id - Moodle Course ID
 * @return <boolean> false if empty, true if not
 */
function kent_course_has_content($course_id){

    global $CFG, $DB;

    // count number of modules in this course
    $no_modules = intval($DB->count_records('course_modules',array('course' => $course_id)));

    // if course has modules return true as it has content
    if (is_int($no_modules) && $no_modules>0) return TRUE;

    // count number of non-empty summaries
    $sql = "SELECT COUNT(id) FROM {$CFG->prefix}course_sections WHERE course={$course_id} AND section!=0 AND summary is not null AND summary !=''";
    $no_modules = (int) $DB->count_records_sql($sql);

    // if there are any non-empty summaries return true as it has content
    if ($no_modules>0) return TRUE;

    // must be empty, return false
    return FALSE;
}
