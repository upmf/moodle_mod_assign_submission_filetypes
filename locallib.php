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
 * This file contains the definition for the library class for file submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package assignsubmission_filetypes
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/eventslib.php');

defined('MOODLE_INTERNAL') || die();

// File areas for file submission assignment.
define('ASSIGNSUBMISSION_FILETYPES_MAXFILES', 20);
define('ASSIGNSUBMISSION_FILETYPES_MAXSUMMARYFILES', 5);
define('ASSIGNSUBMISSION_FILETYPES_FILEAREA', 'submission_filetypes');

/**
 * Library class for file submission plugin extending submission plugin base class
 *
 * @package   assignsubmission_filetypes
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_filetypes extends assign_submission_plugin {

    /**
     * Get the name of the file submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('filetypes', 'assignsubmission_filetypes');
    }

    /**
     * Get file submission information from the database
     *
     * @param int $submissionid
     * @return mixed
     */
    private function get_file_submission($submissionid) {
        global $DB;
        return $DB->get_record('assignsubmission_filetypes', array('submission'=>$submissionid));
    }
/**
     * The list of acceptable extensions by group
     *
     * @return string acceptable file extension
     */
    public function get_accepted_extensions($group) {

        if ($group == 'worddocs'){
            $norm_group = 'document';
        }elseif ($group =='pdfdocs'){
             $norm_group  = 'application/pdf';
        }elseif ($group =='imagedocs'){
             $norm_group = 'image';
        }elseif ($group =='videodocs'){
             $norm_group = 'video';
        }elseif ($group =='audiodocs'){
             $norm_group = 'audio';
	}else{
	     $norm_group = $group;
	}
        $accepted_extensions = file_get_typegroup('extension',$norm_group);
	$label_extensions = get_string($group, 'assignsubmission_filetypes').' ('.implode('/',$accepted_extensions).')';
	return $label_extensions;
    }


    /**
     * Get the default setting for file submission plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $COURSE;

        $worddocs = $this->get_config('worddocs');
        $pdfdocs = $this->get_config('pdfdocs');
        $imagedocs = $this->get_config('imagedocs');
        $videodocs = $this->get_config('videodocs');
        $audiodocs = $this->get_config('audiodocs');
        $otherdocs = $this->get_config('otherdocs');
        $otherdocstext = $this->get_config('otherdocstext');
        $restrictfiletypes = $this->get_config('restrictfiletypes');
        $defaultmaxfilesubmissions = $this->get_config('maxfilesubmissions');
        $defaultmaxsubmissionsizebytes = $this->get_config('maxsubmissionsizebytes');

        $settings = array();
        $options = array();
        for ($i = 1; $i <= ASSIGNSUBMISSION_FILETYPES_MAXFILES; $i++) {
            $options[$i] = $i;
        }

        $name = get_string('maxfilessubmission', 'assignsubmission_filetypes');
        $mform->addElement('select', 'assignsubmission_filetypes_maxfiles', $name, $options);
        $mform->addHelpButton('assignsubmission_filetypes_maxfiles',
                              'maxfilessubmission',
                              'assignsubmission_filetypes');
        $mform->setDefault('assignsubmission_filetypes_maxfiles', $defaultmaxfilesubmissions);
        $mform->disabledIf('assignsubmission_filetypes_maxfiles', 'assignsubmission_filetypes_enabled', 'notchecked');

        $choices = get_max_upload_sizes($CFG->maxbytes,
                                        $COURSE->maxbytes,
                                        get_config('assignsubmission_filetypes', 'maxbytes'));

        $settings[] = array('type' => 'select',
                            'name' => 'maxsubmissionsizebytes',
                            'description' => get_string('maximumsubmissionsize', 'assignsubmission_filetypes'),
                            'options'=> $choices,
                            'default'=> $defaultmaxsubmissionsizebytes);

        $name = get_string('maximumsubmissionsize', 'assignsubmission_filetypes');
        $mform->addElement('select', 'assignsubmission_filetypes_maxsizebytes', $name, $choices);
        $mform->addHelpButton('assignsubmission_filetypes_maxsizebytes',
                              'maximumsubmissionsize',
                              'assignsubmission_filetypes');
        $mform->setDefault('assignsubmission_filetypes_maxsizebytes', $defaultmaxsubmissionsizebytes);
        $mform->disabledIf('assignsubmission_filetypes_maxsizebytes',
                           'assignsubmission_filetypes_enabled',
                           'notchecked');

        require_once("HTML/QuickForm/element.php");
        if (class_exists('HTML_QuickForm')) {
            HTML_QuickForm::registerRule('othertextboxemptycheck', 'function', 'othertextbox_validation', 'assign_submission_filetypes');
        }

        // File types restriction setting.
        $mform->addElement('selectyesno', 'assignsubmission_filetypes_restrictfiletypes', get_string('restrictfiletypes', 'assignsubmission_filetypes'));
        $mform->addHelpButton('assignsubmission_filetypes_restrictfiletypes', 'restrictfiletypes', 'assignsubmission_filetypes');
        $mform->setDefault('assignsubmission_filetypes_restrictfiletypes',  $restrictfiletypes);
        $mform->disabledIf('assignsubmission_filetypes_restrictfiletypes', 'assignsubmission_filetypes_enabled', 'eq', 0);

        // File type checkboxes.

        // Word docs.
        $mform->addElement('advcheckbox', 'assignsubmission_filetypes_worddocs', '', $this->get_accepted_extensions('worddocs'));
        $mform->setDefault('assignsubmission_filetypes_worddocs', $worddocs);
        $mform->disabledIf('assignsubmission_filetypes_worddocs', 'assignsubmission_filetypes_enabled', 'eq', 0);
        $mform->disabledIf('assignsubmission_filetypes_worddocs', 'assignsubmission_filetypes_restrictfiletypes', 'eq', 0);

        // PDF docs.
        $mform->addElement('advcheckbox', 'assignsubmission_filetypes_pdfdocs', '', $this->get_accepted_extensions('pdfdocs'));
        $mform->setDefault('assignsubmission_filetypes_pdfdocs', $pdfdocs);
        $mform->disabledIf('assignsubmission_filetypes_pdfdocs', 'assignsubmission_filetypes_enabled', 'eq', 0);
        $mform->disabledIf('assignsubmission_filetypes_pdfdocs', 'assignsubmission_filetypes_restrictfiletypes', 'eq', 0);

        // Image docs.
        $mform->addElement('advcheckbox', 'assignsubmission_filetypes_imagedocs', '', $this->get_accepted_extensions('imagedocs'));
        $mform->setDefault('assignsubmission_filetypes_imagedocs', $imagedocs);
        $mform->disabledIf('assignsubmission_filetypes_imagedocs', 'assignsubmission_filetypes_enabled', 'eq', 0);
        $mform->disabledIf('assignsubmission_filetypes_imagedocs', 'assignsubmission_filetypes_restrictfiletypes', 'eq', 0);

        // Video docs.
        $mform->addElement('advcheckbox', 'assignsubmission_filetypes_videodocs', '', $this->get_accepted_extensions('videodocs'));
        $mform->setDefault('assignsubmission_filetypes_videodocs', $videodocs);
        $mform->disabledIf('assignsubmission_filetypes_videodocs', 'assignsubmission_filetypes_enabled', 'eq', 0);
        $mform->disabledIf('assignsubmission_filetypes_videodocs', 'assignsubmission_filetypes_restrictfiletypes', 'eq', 0);

        // Audio docs.
        $mform->addElement('advcheckbox', 'assignsubmission_filetypes_audiodocs', '', $this->get_accepted_extensions('audiodocs'));
        $mform->setDefault('assignsubmission_filetypes_audiodocs', $audiodocs);
        $mform->disabledIf('assignsubmission_filetypes_audiodocs', 'assignsubmission_filetypes_enabled', 'eq', 0);
        $mform->disabledIf('assignsubmission_filetypes_audiodocs', 'assignsubmission_filetypes_restrictfiletypes', 'eq', 0);

        // Other docs.
        $mform->addElement('advcheckbox', 'assignsubmission_filetypes_otherdocs', '', get_string('otherdocs', 'assignsubmission_filetypes'));
        $mform->setDefault('assignsubmission_filetypes_otherdocs', $otherdocs);
        $mform->disabledIf('assignsubmission_filetypes_otherdocs', 'assignsubmission_filetypes_enabled', 'eq', 0);
        $mform->disabledIf('assignsubmission_filetypes_otherdocs', 'assignsubmission_filetypes_restrictfiletypes', 'eq', 0);

        // Other docs text
        $mform->addElement('text', 'assignsubmission_filetypes_otherdocstext','','placeholder= "*.xlsx, *.pptx"');
        $mform->setType('assignsubmission_filetypes_otherdocstext', PARAM_TEXT);
        $mform->setDefault('assignsubmission_filetypes_otherdocstext', $otherdocstext);
        $mform->addRule('assignsubmission_filetypes_otherdocstext', get_string('incorrectformatothertext', 'assignsubmission_filetypes'), 'othertextboxemptycheck', null, 'client');
        $mform->disabledIf('assignsubmission_filetypes_otherdocstext', 'assignsubmission_filetypes_otherdocs', 'eq', 0);
        $mform->disabledIf('assignsubmission_filetypes_otherdocstext', 'assignsubmission_filetypes_enabled', 'eq', 0);
        $mform->disabledIf('assignsubmission_filetypes_otherdocstext', 'assignsubmission_filetypes_restrictfiletypes', 'eq', 0);

    }

    /**
     * Registered callback for the addRule function to validate the other textbox validation
     * @param $elementValue  value entered by the user
     * @return boolean
     */
    public static function othertextbox_validation($elementValue) {
        // Must match this patttern : *.etc, *.test
        if (preg_match('/^\*\.[a-zA-Z0-9]+(,\s*\*\.[a-zA-Z0-9]+)*$/i', $elementValue)) {
            return true;
        }

        return false;
    }

    /**
     * Save the settings for file submission plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $this->set_config('maxfilesubmissions', $data->assignsubmission_filetypes_maxfiles);
        $this->set_config('maxsubmissionsizebytes', $data->assignsubmission_filetypes_maxsizebytes);
        $this->set_config('restrictfiletypes', $data->assignsubmission_filetypes_restrictfiletypes);
        $this->set_config('worddocs', $data->assignsubmission_filetypes_worddocs);
        $this->set_config('pdfdocs', $data->assignsubmission_filetypes_pdfdocs);
        $this->set_config('imagedocs', $data->assignsubmission_filetypes_imagedocs);
        $this->set_config('videodocs', $data->assignsubmission_filetypes_videodocs);
        $this->set_config('audiodocs', $data->assignsubmission_filetypes_audiodocs);
        $this->set_config('otherdocs', $data->assignsubmission_filetypes_otherdocs);
        if (isset($data->assignsubmission_filetypes_otherdocstext)) {
            $otherdocstext = str_replace('"',"'",$data->assignsubmission_filetypes_otherdocstext);
            $this->set_config('otherdocstext', $otherdocstext);
        }
        return true;
    }

    /**
     * File format options
     *
     * @return array
     */
    private function get_file_options() {
        $restrictfiletypes = $this->get_config('restrictfiletypes');
        $worddocs = $this->get_config('worddocs');
        $pdfdocs = $this->get_config('pdfdocs');
        $imagedocs = $this->get_config('imagedocs');
        $videodocs = $this->get_config('videodocs');
        $audiodocs = $this->get_config('audiodocs');
        $otherdocs = $this->get_config('otherdocs');
        $otherdocstext = $this->get_config('otherdocstext');

        // check mimetypes
        if ($restrictfiletypes) {
            $worddocs_types = array();
            $pdfdocs_types = array();
            $imagedocs_types = array();
            $videodocs_types = array();
            $audiodocs_types = array();
            $otherdocs_types = array();
            if ($worddocs ) {
                // Word (*.doc, *.docx, *.rtf).
                $worddocs_types = file_get_typegroup('type', array('document'));
            }
            if ($pdfdocs ) {
                // PDF (*.pdf).
                $pdfdocs_types = file_get_typegroup('type', array('application/pdf'));
            }
            if ($imagedocs) {
                // Image (*.gif, *.jpg, *.jpeg, *.png), *.svg, *.tiff).
               $imagedocs_types = file_get_typegroup('type', array('image'));
            }
            if ($videodocs) {
                // Video (*.mp4, *.flv, *.mov, *.avi).
                $videodocs_types = file_get_typegroup('type', array('video'));
            }
            if ($audiodocs) {
                // Audio (*.mp3, *.ogg, *.wav, *.aac, *.wma).
                $audiodocs_types = file_get_typegroup('type', array('audio'));
            }
            if ($otherdocs) {
                // Other file types.
                // The mimetype of 'other' files just can not be verified here as we just dont know what they are and they might not
                // Be in the list (get_mimetypes_array) so if this other checkbox is ticked then we are going to accept
                // All types of files and the validation of the uploaded file(s) extensions will be done when the assignment is
                // Submmited not in the file picker any longer
                //$otherdocs_types =  array('*');
                $cleaneddocs_types = str_replace(array(' ','*'),'',$otherdocstext);
                $filetypes = explode(',', $cleaneddocs_types);
                $otherdocs_types = file_get_typegroup('type',$filetypes);
            }
            $accepted_types = array_merge($worddocs_types, $pdfdocs_types, $imagedocs_types,
                                          $videodocs_types, $audiodocs_types, $otherdocs_types);
        } else {
            $accepted_types = '*';
        }

        $fileoptions = array('subdirs'=>1,
                                'maxbytes'=>$this->get_config('maxsubmissionsizebytes'),
                                'maxfiles'=>$this->get_config('maxfilesubmissions'),
                                'accepted_types'=> $accepted_types,
                                'return_types'=>FILE_INTERNAL);
        return $fileoptions;
    }

    /**
     * Add elements to submission form
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {

        if ($this->get_config('maxfilesubmissions') <= 0) {
            return false;
        }

        $fileoptions = $this->get_file_options();
        $submissionid = $submission ? $submission->id : 0;
        $rawfiletypes = $this->acceptable_files();

        if (!empty($rawfiletypes)) {
            $acceptablefiletypes = array_values($rawfiletypes);
            $filetypes = '';
            foreach ($acceptablefiletypes as $filetype) {
                $filetypes .= html_writer::tag('li', $filetype);
            }
            // List of acceptable file types.
            $filetypelist = html_writer::tag('ul', $filetypes);
            $mform->addElement('html', get_string('permittedfiletypes', 'assignsubmission_filetypes', $filetypelist));
        }

        $data = file_prepare_standard_filemanager($data,
                                                  'files',
                                                  $fileoptions,
                                                  $this->assignment->get_context(),
                                                  'assignsubmission_filetypes',
                                                  ASSIGNSUBMISSION_FILETYPES_FILEAREA,
                                                  $submissionid);
        $mform->addElement('filemanager', 'files_filemanager', $this->get_name(), null, $fileoptions);

        return true;
    }

    /**
     * Count the number of files
     *
     * @param int $submissionid
     * @param string $area
     * @return int
     */
    private function count_files($submissionid, $area) {

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->assignment->get_context()->id,
                                     'assignsubmission_filetypes',
                                     $area,
                                     $submissionid,
                                     'id',
                                     false);

        return count($files);
    }

    /**
     * Save the files and trigger plagiarism plugin, if enabled,
     * to scan the uploaded files via events trigger
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $USER, $DB;

        $fileoptions = $this->get_file_options();

        $data = file_postupdate_standard_filemanager($data,
                                                     'files',
                                                     $fileoptions,
                                                     $this->assignment->get_context(),
                                                     'assignsubmission_filetypes',
                                                     ASSIGNSUBMISSION_FILETYPES_FILEAREA,
                                                     $submission->id);

        $filesubmission = $this->get_file_submission($submission->id);

        // Plagiarism code event trigger when files are uploaded.

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->assignment->get_context()->id,
                                     'assignsubmission_filetypes',
                                     ASSIGNSUBMISSION_FILETYPES_FILEAREA,
                                     $submission->id,
                                     'id',
                                     false);

        $count = $this->count_files($submission->id, ASSIGNSUBMISSION_FILETYPES_FILEAREA);

        $params = array(
            'context' => context_module::instance($this->assignment->get_course_module()->id),
            'courseid' => $this->assignment->get_course()->id,
            'objectid' => $submission->id,
            'other' => array(
                'content' => '',
                'pathnamehashes' => array_keys($files)
            )
        );
        if (!empty($submission->userid) && ($submission->userid != $USER->id)) {
            $params['relateduserid'] = $submission->userid;
        }
        $event = \assignsubmission_filetypes\event\assessable_uploaded::create($params);
        $event->set_legacy_files($files);
        $event->trigger();

        $groupname = null;
        $groupid = 0;
        // Get the group name as other fields are not transcribed in the logs and this information is important.
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupname = $DB->get_field('groups', 'name', array('id' => $submission->groupid), '*', MUST_EXIST);
            $groupid = $submission->groupid;
        } else {
            $params['relateduserid'] = $submission->userid;
        }

        // Unset the objectid and other field from params for use in submission events.
        unset($params['objectid']);
        unset($params['other']);
        $params['other'] = array(
            'submissionid' => $submission->id,
            'submissionattempt' => $submission->attemptnumber,
            'submissionstatus' => $submission->status,
            'filesubmissioncount' => $count,
            'groupid' => $groupid,
            'groupname' => $groupname
        );

        if ($filesubmission) {
            $filesubmission->numfiles = $this->count_files($submission->id,
                                                           ASSIGNSUBMISSION_FILETYPES_FILEAREA);
            $updatestatus = $DB->update_record('assignsubmission_filetypes', $filesubmission);
            $params['objectid'] = $filesubmission->id;

            $event = \assignsubmission_filetypes\event\submission_updated::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();
            return $updatestatus;
        } else {
            $filesubmission = new stdClass();
            $filesubmission->numfiles = $this->count_files($submission->id,
                                                           ASSIGNSUBMISSION_FILETYPES_FILEAREA);
            $filesubmission->submission = $submission->id;
            $filesubmission->assignment = $this->assignment->get_instance()->id;
            $filesubmission->id = $DB->insert_record('assignsubmission_filetypes', $filesubmission);
            $params['objectid'] = $filesubmission->id;

            $event = \assignsubmission_filetypes\event\submission_created::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();
            return $filesubmission->id > 0;
        }
    }

    /**
     * Produce a list of files suitable for export that represent this feedback or submission
     *
     * @param stdClass $submission The submission
     * @param stdClass $user The user record - unused
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user) {
        $result = array();
        $fs = get_file_storage();

        $files = $fs->get_area_files($this->assignment->get_context()->id,
                                     'assignsubmission_filetypes',
                                     ASSIGNSUBMISSION_FILETYPES_FILEAREA,
                                     $submission->id,
                                     'timemodified',
                                     false);

        foreach ($files as $file) {
            $result[$file->get_filename()] = $file;
        }
        return $result;
    }
    /**
     * The list of acceptable files
     *
     * @return array of acceptable file types
     */
    public function acceptable_files() {

        // Restrict file type is not enabled.
        if(!$this->get_config('restrictfiletypes')) {
            return array();
        }

        $worddocs = $this->get_config('worddocs');
        $pdfdocs = $this->get_config('pdfdocs');
        $imagedocs = $this->get_config('imagedocs');
        $videodocs = $this->get_config('videodocs');
        $audiodocs = $this->get_config('audiodocs');
        $otherdocs = $this->get_config('otherdocs');
        $otherdocstext = $this->get_config('otherdocstext');
        $accepted_types = array();
        $worddocs_types = array();
        $pdfdocs_types = array();
        $imagedocs_types = array();
        $videodocs_types = array();
        $audiodocs_types = array();
        $otherdocs_types = array();
        $arraydiffer = array();

        if ($worddocs) {
            // Word (*.doc, *.docx, *.rtf).
            $worddocs_types = array($this->get_accepted_extensions('worddocs'));
        }
        if ($pdfdocs) {
            // PDF (*.pdf).
            $pdfdocs_types = array($this->get_accepted_extensions('pdfdocs'));
        }
        if ($imagedocs) {
            // Image (*.gif, *.jpg, *.jpeg, *.png), *.svg, *.tiff).
            $imagedocs_types = array($this->get_accepted_extensions('imagedocs'));
        }
        if ($videodocs) {
            // Video (*.mp4, *.flv, *.mov, *.avi).
            $videodocs_types = array($this->get_accepted_extensions('videodocs'));
        }
        if ($audiodocs) {
            // Audio (*.mp3, *.ogg, *.wav, *.aac, *.wma).
            $audiodocs_types = array($this->get_accepted_extensions('audiodocs'));
        }

        if ($otherdocs) {
            $cleaneddocs_types = array();
            $nowhitespace = str_replace(' ','',$otherdocstext);
            $filetypes = explode('*', $nowhitespace);
            foreach ($filetypes as $key => $filetype) {
                $cleaneddocs_types[$key] = str_replace(',','', $filetype);
            }
            array_shift($cleaneddocs_types); // Skipping 0 index as it is always empty value.
            $otherdocs_types = $cleaneddocs_types;
        }
        $accepted_types = array_merge($worddocs_types, $pdfdocs_types, $imagedocs_types,
                                      $videodocs_types, $audiodocs_types, $otherdocs_types);

        return $accepted_types;

    }

    /**
     * Display the list of files  in the submission status table
     *
     * @param stdClass $submission
     * @param bool $showviewlink Set this to true if the list of files is long
     * @return string
     */
    public function view_summary(stdClass $submission, & $showviewlink) {
        $count = $this->count_files($submission->id, ASSIGNSUBMISSION_FILETYPES_FILEAREA);

        // Show we show a link to view all files for this plugin?
        $showviewlink = $count > ASSIGNSUBMISSION_FILETYPES_MAXSUMMARYFILES;
        if ($count <= ASSIGNSUBMISSION_FILETYPES_MAXSUMMARYFILES) {
            return $this->assignment->render_area_files('assignsubmission_filetypes',
                                                        ASSIGNSUBMISSION_FILETYPES_FILEAREA,
                                                        $submission->id);
        } else {
            return get_string('countfiles', 'assignsubmission_filetypes', $count);
        }
    }

    /**
     * No full submission view - the summary contains the list of files and that is the whole submission
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        return $this->assignment->render_area_files('assignsubmission_filetypes',
                                                    ASSIGNSUBMISSION_FILETYPES_FILEAREA,
                                                    $submission->id);
    }



    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type
     * and version.
     *
     * @param string $type
     * @param int $version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version) {

        $uploadsingletype ='uploadsingle';
        $uploadtype ='upload';

        if (($type == $uploadsingletype || $type == $uploadtype) && $version >= 2011112900) {
            return true;
        }
        return false;
    }


    /**
     * Upgrade the settings from the old assignment
     * to the new plugin based one
     *
     * @param context $oldcontext - the old assignment context
     * @param stdClass $oldassignment - the old assignment data record
     * @param string $log record log events here
     * @return bool Was it a success? (false will trigger rollback)
     */
    public function upgrade_settings(context $oldcontext, stdClass $oldassignment, & $log) {
        global $DB;

        if ($oldassignment->assignmenttype == 'uploadsingle') {
            $this->set_config('maxfilesubmissions', 1);
            $this->set_config('maxsubmissionsizebytes', $oldassignment->maxbytes);
            return true;
        } else if ($oldassignment->assignmenttype == 'upload') {
            $this->set_config('maxfilesubmissions', $oldassignment->var1);
            $this->set_config('maxsubmissionsizebytes', $oldassignment->maxbytes);

            // Advanced file upload uses a different setting to do the same thing.
            $DB->set_field('assign',
                           'submissiondrafts',
                           $oldassignment->var4,
                           array('id'=>$this->assignment->get_instance()->id));

            // Convert advanced file upload "hide description before due date" setting.
            $alwaysshow = 0;
            if (!$oldassignment->var3) {
                $alwaysshow = 1;
            }
            $DB->set_field('assign',
                           'alwaysshowdescription',
                           $alwaysshow,
                           array('id'=>$this->assignment->get_instance()->id));
            return true;
        }
    }

    /**
     * Upgrade the submission from the old assignment to the new one
     *
     * @param context $oldcontext The context of the old assignment
     * @param stdClass $oldassignment The data record for the old oldassignment
     * @param stdClass $oldsubmission The data record for the old submission
     * @param stdClass $submission The data record for the new submission
     * @param string $log Record upgrade messages in the log
     * @return bool true or false - false will trigger a rollback
     */
    public function upgrade(context $oldcontext,
                            stdClass $oldassignment,
                            stdClass $oldsubmission,
                            stdClass $submission,
                            & $log) {
        global $DB;

        $filesubmission = new stdClass();

        $filesubmission->numfiles = $oldsubmission->numfiles;
        $filesubmission->submission = $submission->id;
        $filesubmission->assignment = $this->assignment->get_instance()->id;

        if (!$DB->insert_record('assignsubmission_filetypes', $filesubmission) > 0) {
            $log .= get_string('couldnotconvertsubmission', 'mod_assign', $submission->userid);
            return false;
        }

        // Now copy the area files.
        $this->assignment->copy_area_files_for_upgrade($oldcontext->id,
                                                        'mod_assignment',
                                                        'submission',
                                                        $oldsubmission->id,
                                                        $this->assignment->get_context()->id,
                                                        'assignsubmission_filetypes',
                                                        ASSIGNSUBMISSION_FILETYPES_FILEAREA,
                                                        $submission->id);

        return true;
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        // Will throw exception on failure.
        $DB->delete_records('assignsubmission_filetypes',
                            array('assignment'=>$this->assignment->get_instance()->id));

        return true;
    }

    /**
     * Formatting for log info
     *
     * @param stdClass $submission The submission
     * @return string
     */
    public function format_for_log(stdClass $submission) {
        // Format the info for each submission plugin (will be added to log).
        $filecount = $this->count_files($submission->id, ASSIGNSUBMISSION_FILETYPES_FILEAREA);

        return get_string('numfilesforlog', 'assignsubmission_filetypes', $filecount);
    }

    /**
     * Return true if there are no submission files
     * @param stdClass $submission
     */
    public function is_empty(stdClass $submission) {
        return $this->count_files($submission->id, ASSIGNSUBMISSION_FILETYPES_FILEAREA) == 0;
    }

    /**
     * Get file areas returns a list of areas this plugin stores files
     * @return array - An array of fileareas (keys) and descriptions (values)
     */
    public function get_file_areas() {
        return array(ASSIGNSUBMISSION_FILETYPES_FILEAREA=>$this->get_name());
    }

    /**
     * Copy the student's submission from a previous submission. Used when a student opts to base their resubmission
     * on the last submission.
     * @param stdClass $sourcesubmission
     * @param stdClass $destsubmission
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        // Copy the files across.
        $contextid = $this->assignment->get_context()->id;
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid,
                                     'assignsubmission_filetypes',
                                     ASSIGNSUBMISSION_FILETYPES_FILEAREA,
                                     $sourcesubmission->id,
                                     'id',
                                     false);
        foreach ($files as $file) {
            $fieldupdates = array('itemid' => $destsubmission->id);
            $fs->create_file_from_storedfile($fieldupdates, $file);
        }

        // Copy the assignsubmission_filetypes record.
        if ($filesubmission = $this->get_file_submission($sourcesubmission->id)) {
            unset($filesubmission->id);
            $filesubmission->submission = $destsubmission->id;
            $DB->insert_record('assignsubmission_filetypes', $filesubmission);
        }
        return true;
    }

    /**
     * Return a description of external params suitable for uploading a file submission from a webservice.
     *
     * @return external_description|null
     */
    public function get_external_parameters() {
        return array(
            'files_filemanager' => new external_value(
                PARAM_INT,
                'The id of a draft area containing files for this submission.'
            )
        );
    }
}
