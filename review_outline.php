<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Course Generator - Step 2: Review outline and create the Moodle course.
 *
 * Text-based flow: Uses the outline from agent chat + source text to generate
 * lesson content directly, without needing Lumination course/document UUIDs.
 *
 * URL: /local/lumination/review_outline.php
 *
 * @package    local_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/lumination:generatecourse', $context);

$PAGE->set_url(new moodle_url('/local/lumination/review_outline.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('outlinereview', 'local_lumination'));
$PAGE->set_heading(get_string('outlinereview', 'local_lumination'));
$PAGE->set_pagelayout('standard');

// Retrieve data from session.
$outline = $SESSION->lumination_outline ?? null;
$sourcetext = $SESSION->lumination_source_text ?? '';
$title = $SESSION->lumination_title ?? '';
$categoryid = $SESSION->lumination_categoryid ?? 1;
$language = $SESSION->lumination_language ?? 'en';

if (empty($outline)) {
    redirect(new moodle_url('/local/lumination/course_generator.php'));
}

$form = new \local_lumination\form\review_outline_form(null, [
    'outline' => $outline,
    'guide_uuid' => '',
    'title' => $title,
    'categoryid' => $categoryid,
    'document_uuids' => [],
    'language' => $language,
]);

if ($form->is_cancelled()) {
    unset($SESSION->lumination_outline, $SESSION->lumination_source_text,
        $SESSION->lumination_title, $SESSION->lumination_categoryid, $SESSION->lumination_language);
    redirect(new moodle_url('/local/lumination/course_generator.php'));
}

if ($data = $form->get_data()) {
    $api = new \local_lumination\api_client();
    $generator = new \local_lumination\course_generator($api);

    $editedmodules = $form->get_edited_outline();
    $coursetitle = $data->title;
    $categoryid = $data->categoryid;

    try {
        $course = $generator->create_moodle_course_from_text(
            $editedmodules,
            $coursetitle,
            $categoryid,
            $sourcetext,
            $language
        );

        // Clear session.
        unset($SESSION->lumination_outline, $SESSION->lumination_source_text,
            $SESSION->lumination_title, $SESSION->lumination_categoryid, $SESSION->lumination_language);

        // Show success.
        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            get_string('coursecreated_desc', 'local_lumination', (object)[
                'sections' => $course->lumination_sections,
                'activities' => $course->lumination_activities,
            ]),
            'success'
        );
        echo html_writer::link(
            new moodle_url('/course/view.php', ['id' => $course->id]),
            get_string('viewcourse', 'local_lumination'),
            ['class' => 'btn btn-primary mt-3']
        );
        echo $OUTPUT->footer();
        die;

    } catch (\Exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            get_string('errorapifailed', 'local_lumination', $e->getMessage()), 'error');
        $form->display();
        echo $OUTPUT->footer();
        die;
    }
}

// Prepare JS strings for the inline outline editor.
$jsstrings = json_encode([
    'module' => get_string('modulename', 'local_lumination'),
    'lesson' => get_string('lessonname', 'local_lumination'),
    'addmodule' => get_string('addmodule', 'local_lumination'),
    'removemodule' => get_string('removemodule', 'local_lumination'),
    'addlesson' => get_string('addlesson', 'local_lumination'),
    'removelesson' => get_string('removelesson', 'local_lumination'),
    'generatingcourse' => get_string('generatingcourse', 'local_lumination'),
    'generatingcourse_desc' => get_string('generatingcourse_desc', 'local_lumination'),
]);

// Display the review form.
echo $OUTPUT->header();
echo html_writer::tag('p', get_string('outlinereview_desc', 'local_lumination'));
$form->display();

// Inline JS for the outline editor — avoids AMD build pipeline complexity.
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    var STRINGS = ' . $jsstrings . ';
    var container = document.getElementById("lumination-outline-editor");
    var hiddenField = document.querySelector("input[name=outline_json]");
    if (!container || !hiddenField) return;

    var modules;
    try { modules = JSON.parse(hiddenField.value); } catch(e) { modules = []; }

    function escapeHtml(s) {
        var d = document.createElement("div"); d.textContent = s; return d.innerHTML;
    }

    function render() {
        container.innerHTML = "";
        modules.forEach(function(mod, mi) {
            var card = document.createElement("div");
            card.className = "lumination-module card mb-3";
            card.innerHTML =
                "<div class=\"card-header d-flex justify-content-between align-items-center\">" +
                    "<span class=\"font-weight-bold\">" + STRINGS.module + " " + (mi+1) + "</span>" +
                    "<button type=\"button\" class=\"btn btn-sm btn-outline-danger lum-remove-module\" data-index=\"" + mi + "\">" +
                        STRINGS.removemodule + "</button>" +
                "</div>" +
                "<div class=\"card-body\"></div>";
            var body = card.querySelector(".card-body");

            // Module title.
            var titleGroup = document.createElement("div");
            titleGroup.className = "form-group mb-3";
            titleGroup.innerHTML =
                "<label class=\"font-weight-bold\">" + STRINGS.module + " title</label>" +
                "<input type=\"text\" class=\"form-control lum-module-title\" data-module=\"" + mi +
                "\" value=\"" + escapeHtml(mod.title || "") + "\">";
            body.appendChild(titleGroup);

            // Lessons.
            (mod.lessons || []).forEach(function(lesson, li) {
                var row = document.createElement("div");
                row.className = "input-group mb-2 lum-lesson-row";
                row.innerHTML =
                    "<div class=\"input-group-prepend\">" +
                        "<span class=\"input-group-text\">" + STRINGS.lesson + " " + (li+1) + "</span>" +
                    "</div>" +
                    "<input type=\"text\" class=\"form-control lum-lesson-title\" data-module=\"" + mi +
                    "\" data-lesson=\"" + li + "\" value=\"" + escapeHtml(lesson.title || "") + "\">" +
                    "<div class=\"input-group-append\">" +
                        "<button type=\"button\" class=\"btn btn-outline-danger btn-sm lum-remove-lesson\" " +
                        "data-module=\"" + mi + "\" data-lesson=\"" + li + "\">&times;</button>" +
                    "</div>";
                body.appendChild(row);
            });

            // Add lesson button.
            var addLessonBtn = document.createElement("button");
            addLessonBtn.type = "button";
            addLessonBtn.className = "btn btn-sm btn-outline-secondary mt-1";
            addLessonBtn.textContent = "+ " + STRINGS.addlesson;
            addLessonBtn.setAttribute("data-module", mi);
            addLessonBtn.classList.add("lum-add-lesson");
            body.appendChild(addLessonBtn);

            container.appendChild(card);
        });

        // Add module button.
        var addModBtn = document.createElement("button");
        addModBtn.type = "button";
        addModBtn.className = "btn btn-outline-primary mb-3 lum-add-module";
        addModBtn.textContent = "+ " + STRINGS.addmodule;
        container.appendChild(addModBtn);
    }

    function sync() {
        // Read current values from inputs back into modules array.
        container.querySelectorAll(".lumination-module").forEach(function(card, mi) {
            if (!modules[mi]) return;
            var titleInput = card.querySelector(".lum-module-title");
            if (titleInput) modules[mi].title = titleInput.value;
            var lessonInputs = card.querySelectorAll(".lum-lesson-title");
            lessonInputs.forEach(function(inp, li) {
                if (modules[mi].lessons && modules[mi].lessons[li]) {
                    modules[mi].lessons[li].title = inp.value;
                }
            });
        });
        hiddenField.value = JSON.stringify(modules);
    }

    // Event delegation.
    container.addEventListener("click", function(e) {
        var btn = e.target.closest("button");
        if (!btn) return;

        sync(); // Save current input values first.

        if (btn.classList.contains("lum-add-module")) {
            modules.push({title: "", lessons: [{title: ""}]});
            render();
        } else if (btn.classList.contains("lum-remove-module")) {
            if (modules.length <= 1) return;
            modules.splice(parseInt(btn.dataset.index), 1);
            render();
        } else if (btn.classList.contains("lum-add-lesson")) {
            var mi = parseInt(btn.dataset.module);
            modules[mi].lessons.push({title: ""});
            render();
        } else if (btn.classList.contains("lum-remove-lesson")) {
            var mi2 = parseInt(btn.dataset.module);
            var li = parseInt(btn.dataset.lesson);
            if (modules[mi2].lessons.length <= 1) return;
            modules[mi2].lessons.splice(li, 1);
            render();
        }
        sync();
    });

    container.addEventListener("input", function() { sync(); });

    // Loading overlay on form submit.
    container.closest("form").addEventListener("submit", function() {
        sync();
        var overlay = document.createElement("div");
        overlay.id = "lumination-loading-overlay";
        overlay.style.cssText = "position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;display:flex;align-items:center;justify-content:center;";
        overlay.innerHTML =
            "<div style=\"background:#fff;border-radius:8px;padding:2rem 3rem;text-align:center;max-width:420px;\">" +
                "<div class=\"spinner-border text-primary mb-3\" role=\"status\"><span class=\"sr-only\">Loading...</span></div>" +
                "<h4>" + escapeHtml(STRINGS.generatingcourse) + "</h4>" +
                "<p class=\"text-muted mb-0\">" + escapeHtml(STRINGS.generatingcourse_desc) + "</p>" +
            "</div>";
        document.body.appendChild(overlay);
    });

    render();
    sync();
});
</script>';

echo $OUTPUT->footer();
