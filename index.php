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
 * Report main page
 *
 * @package    report
 * @copyright  2020 Paulo Jr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require __DIR__ . '/../../config.php';
require_once $CFG->libdir . '/adminlib.php';
require_once __DIR__ . '/report_modstats_categories_form.php';
require_once __DIR__ . '/constants.php';

admin_externalpage_setup('reportmodstats', '', null, '', array('pagelayout' => 'report'));

$category = optional_param('category', REPORT_MODSTATS_ALL_CATEGORIES, PARAM_INT);

echo $OUTPUT->header();

$mform = new report_modstats_categories_form();
$mform->display();

$selected_interval = '';

if (isset($_POST['select-menu'])) {
    $selected_interval = $_POST['select-menu'];
}

if ($category == REPORT_MODSTATS_ALL_CATEGORIES) {
    $data = $DB->get_records_sql(
        'SELECT C.fullname AS fullname, M.name, M.id, COUNT(CM.id) AS amount
        FROM {modules} AS M
        JOIN {course_modules} AS CM ON M.id = CM.module
        JOIN {course} AS C ON C.id = CM.course
        WHERE C.visible = 1
        GROUP BY C.fullname'
    );

    $total = $DB->count_records_sql(
        'SELECT COUNT(CM.id) 
        FROM {course} AS C 
        INNER JOIN {course_modules} AS CM ON C.id = CM.course 
        WHERE C.visible = 1'
    );
} else {
    $data = $DB->get_records_sql(
        'SELECT C.fullname AS fullname,
            COUNT(CM.id) AS amount,
            COUNT(CASE WHEN M.name = "quiz" THEN 1 END) AS tests,
            COUNT(CASE WHEN M.name = "resource" THEN 1 END) AS resources
        FROM {modules} AS M
        JOIN {course_modules} AS CM ON M.id = CM.module
        JOIN {course} AS C ON C.id = CM.course
        WHERE C.visible = 1 AND C.category = :cat
        GROUP BY C.fullname',
        array("cat" => $category)
    );

    $total = $DB->count_records_sql(
        'SELECT COUNT(CM.id) 
        FROM {course} AS C 
        INNER JOIN {course_modules} AS CM ON C.id = CM.course 
        WHERE C.visible = 1 AND C.category = :cat',
        array("cat" => $category)
    );
}

$completion_csv_data = array();



if ($total > 0) {
    $chart_labels = array();
    $chart_values = array();



    $table = new html_table();
    $table->size = array( '50%', '50%');
    $table->head = array('L??trehozott modulok', 'Kurzus neve');



    foreach ($data as $item) {
        $row = array();
        $row[] = $item->amount;
        $row[] = $item->fullname;


        $completion = $item->amount;
        $chart_labels[] = $item->fullname;
        $chart_values[] = $completion;

        $table->data[] = $row;
        $completion_csv_data[] = $row;
    }

    if (class_exists('core\chart_bar')) {
        $chart = new core\chart_bar();
        $serie = new core\chart_series(
            get_string('lb_chart_serie', 'report_modstats'), $chart_values
        );
        $chart->add_series($serie);
        $chart->set_labels($chart_labels);
        echo $OUTPUT->render_chart($chart, false);
    }

    echo html_writer::table($table);
}

$head = array('??sszes tev??kenys??g', 'Kurzus neve', 'Felhaszn??l?? neve', 'L??trehozva', 'Friss??tve', 'PDF', 'TXT', 'WORD', 'PPT', 'Vide??');

echo '<h1> ??sszes adat </h1>';

$all_table = new html_table();
$all_table->head = $head;

$all_csv_data = array();
$all_data = $DB->get_records_sql(
    'SELECT
    @row_number:=@row_number+1 AS "Sorsz??m",
    COUNT(CASE WHEN f.filesize != 0 THEN 1 END) AS amount,
    c.fullname AS coursename,
    u.username AS username,
    u.firstname,
    u.lastname,
    u.email,
    COUNT(CASE WHEN l.action = "created" AND f.filesize != 0 THEN 1 END) AS "Created",
    COUNT(CASE WHEN l.action = "updated" AND f.filesize != 0 THEN 1 END) AS "Updated",
    COUNT(CASE WHEN mimetype = "application/pdf" AND l.action = "created" THEN 1 END) AS pdf,
    COUNT(CASE WHEN mimetype = "text/plain" AND l.action = "created" THEN 1 END) AS txt,
    COUNT(CASE WHEN mimetype = "application/vnd.openxmlformats-officedocument.wordprocessingml.document" AND l.action = "created" THEN 1 END) AS word,
    COUNT(CASE WHEN mimetype = "application/vnd.openxmlformats-officedocument.presentationml.presentation" AND l.action = "created" THEN 1 END) AS ppt,
    COUNT(CASE WHEN mimetype = "video/mp4" AND l.action = "created" THEN 1 END) AS video,
    FROM_UNIXTIME(l.timecreated) AS "L??trehoz??s ideje",
    NOW() AS "Lek??rdez??s ideje"
FROM
    mdl_course c
        JOIN mdl_logstore_standard_log l ON l.courseid = c.id
        JOIN mdl_user u ON u.id = l.userid
        JOIN mdl_course_categories cc ON cc.id = c.category
        JOIN mdl_files f ON f.contextid = l.contextid
        JOIN (SELECT @row_number := 0) r
WHERE
        cc.id = :cat AND l.target = "course_module" AND (l.action = "created" OR l.action = "updated") 
GROUP BY l.userid, l.courseid', array("cat" => $category)
);
foreach ($all_data as $item) {

    $row = array();
    $row[] = $item->amount;
    $row[] = $item->coursename;
    $row[] = $item->username;
    $row[] = $item->created;
    $row[] = $item->updated;
    $row[] = $item->pdf;
    $row[] = $item->txt;
    $row[] = $item->word;
    $row[] = $item->ppt;
    $row[] = $item->video;

    $all_table->data[] = $row;

    $all_csv_data[] = $row;
}

echo html_writer::table($all_table);

echo '<h1> Elm??lt 1 h??t </h1>';

$interval_table = new html_table();
$interval_table->head = $head;

$interval_csv_data = array();
$interval_data = $DB->get_records_sql(
    'SELECT
    @row_number:=@row_number+1 AS "Sorsz??m",
    COUNT(CASE WHEN f.filesize != 0 THEN 1 END) AS amount,
    c.fullname AS coursename,
    u.username AS username,
    u.firstname,
    u.lastname,
    u.email,
    COUNT(CASE WHEN l.action = "created" AND f.filesize != 0 THEN 1 END) AS "Created",
    COUNT(CASE WHEN l.action = "updated" AND f.filesize != 0 THEN 1 END) AS "Updated",
    COUNT(CASE WHEN mimetype = "application/pdf" AND l.action = "created" THEN 1 END) AS pdf,
    COUNT(CASE WHEN mimetype = "text/plain" AND l.action = "created" THEN 1 END) AS txt,
    COUNT(CASE WHEN mimetype = "application/vnd.openxmlformats-officedocument.wordprocessingml.document" AND l.action = "created" THEN 1 END) AS word,
    COUNT(CASE WHEN mimetype = "application/vnd.openxmlformats-officedocument.presentationml.presentation" AND l.action = "created" THEN 1 END) AS ppt,
    COUNT(CASE WHEN mimetype = "video/mp4" AND l.action = "created" THEN 1 END) AS video,
    FROM_UNIXTIME(l.timecreated) AS "L??trehoz??s ideje",
    NOW() AS "Lek??rdez??s ideje"
FROM
    mdl_course c
        JOIN mdl_logstore_standard_log l ON l.courseid = c.id
        JOIN mdl_user u ON u.id = l.userid
        JOIN mdl_course_categories cc ON cc.id = c.category
        JOIN mdl_files f ON f.contextid = l.contextid
        JOIN (SELECT @row_number := 0) r
WHERE
        cc.id = :cat AND l.target = "course_module" AND (l.action = "created" OR l.action = "updated") AND l.timecreated > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 WEEK))
GROUP BY l.userid, l.courseid', array("cat" => $category)
);
foreach ($interval_data as $item) {

    $row = array();
    $row[] = $item->amount;
    $row[] = $item->coursename;
    $row[] = $item->username;
    $row[] = $item->created;
    $row[] = $item->updated;
    $row[] = $item->pdf;
    $row[] = $item->txt;
    $row[] = $item->word;
    $row[] = $item->ppt;
    $row[] = $item->video;

    $interval_table->data[] = $row;

    $interval_csv_data[] = $row;
}


echo html_writer::table($interval_table);

echo '<h1>Export??l??s</h1>';

//echo '<form method="post" action="">
//            <select id="select-menu" name="select-menu">
//            <option disabled value="default">V??lassz id??tartamot</option>
//              <option value="1 DAY">1 nap</option>
//              <option value="2 DAY">2 nap</option>
//              <option value="3 DAY">3 nap</option>
//              <option value="4 DAY">4 nap</option>
//              <option value="5 DAY">5 nap</option>
//              <option value="6 DAY">6 nap</option>
//              <option value="1 WEEK">1 h??t</option>
//              <option value="2 WEEK">2 h??t</option>
//              <option value="3 WEEK">3 h??t</option>
//            </select>
//            <br>
//            <input type="submit" value="Friss??t">
//    </form>';


echo '<a id="interval" href="' . write_csv($interval_csv_data, 'interval.csv') . '">Az elm??lt 1 h??t adatainak let??lt??se</a> <br>';
echo '<a id="all" href="' . write_csv($all_csv_data, 'all.csv') . '">??sszes adat let??lt??se</a> <br>';
echo '<a href="' . write_csv($completion_csv_data, 'completion.csv') . '">Kurzus felt??lt??tts??g let??lt??se</a>';


echo $OUTPUT->footer();


function write_csv($data, $filename) {
    $path = './adatok/';
    $csv = fopen($path . $filename, 'w');
    foreach ($data as $line) {
        fputcsv($csv, $line);
    }
    fclose($csv);

    return $path . $filename;
}

function initTable() {

}

?>

<!--<script>-->
<!--  const selectMenu = document.getElementById("select-menu");-->
<!--  const resultDiv = document.getElementById("interval");-->
<!---->
<!--  selectMenu.addEventListener("change", () => {-->
<!--    const selectedValue = selectMenu.options[selectMenu.selectedIndex].text;-->
<!---->
<!--    resultDiv.textContent = "Az elm??lt " + selectedValue + " adatainak let??lt??se";-->
<!--  });-->
<!--</script>-->
