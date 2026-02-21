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
 * AMD module for the outline editor in the course review step.
 *
 * Provides interactive add/remove of modules and lessons in the generated
 * course outline before final course creation.
 *
 * @module     local_lumination/outline_editor
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Escape HTML special characters to prevent XSS.
 *
 * @param {string} s The string to escape.
 * @returns {string} The escaped string.
 */
const escapeHtml = (s) => {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
};

/**
 * Initialise the outline editor.
 *
 * @param {object} params Configuration parameters.
 * @param {object} params.strings Localised UI strings.
 * @param {string} params.strings.module Module label.
 * @param {string} params.strings.lesson Lesson label.
 * @param {string} params.strings.addmodule Add-module button text.
 * @param {string} params.strings.removemodule Remove-module button text.
 * @param {string} params.strings.addlesson Add-lesson button text.
 * @param {string} params.strings.removelesson Remove-lesson button text.
 * @param {string} params.strings.generatingcourse Loading overlay title.
 * @param {string} params.strings.generatingcourse_desc Loading overlay description.
 */
export const init = (params) => {
    const STRINGS = params.strings;
    const container = document.getElementById('lumination-outline-editor');
    const hiddenField = document.querySelector('input[name=outline_json]');
    if (!container || !hiddenField) {
        return;
    }

    let modules;
    try {
        modules = JSON.parse(hiddenField.value);
    } catch (e) {
        modules = [];
    }

    /**
     * Render the outline editor UI from the modules array.
     */
    const render = () => {
        container.innerHTML = '';
        modules.forEach((mod, mi) => {
            const card = document.createElement('div');
            card.className = 'lumination-module card mb-3';
            card.innerHTML =
                '<div class="card-header d-flex justify-content-between align-items-center">' +
                    '<span class="font-weight-bold">' + STRINGS.module + ' ' + (mi + 1) + '</span>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger lum-remove-module" data-index="' +
                        mi + '">' +
                        STRINGS.removemodule + '</button>' +
                '</div>' +
                '<div class="card-body"></div>';
            const body = card.querySelector('.card-body');

            // Module title.
            const titleGroup = document.createElement('div');
            titleGroup.className = 'form-group mb-3';
            titleGroup.innerHTML =
                '<label class="font-weight-bold">' + STRINGS.module + ' title</label>' +
                '<input type="text" class="form-control lum-module-title" data-module="' + mi +
                '" value="' + escapeHtml(mod.title || '') + '">';
            body.appendChild(titleGroup);

            // Lessons.
            (mod.lessons || []).forEach((lesson, li) => {
                const row = document.createElement('div');
                row.className = 'input-group mb-2 lum-lesson-row';
                row.innerHTML =
                    '<div class="input-group-prepend">' +
                        '<span class="input-group-text">' + STRINGS.lesson + ' ' + (li + 1) + '</span>' +
                    '</div>' +
                    '<input type="text" class="form-control lum-lesson-title" data-module="' + mi +
                    '" data-lesson="' + li + '" value="' + escapeHtml(lesson.title || '') + '">' +
                    '<div class="input-group-append">' +
                        '<button type="button" class="btn btn-outline-danger btn-sm lum-remove-lesson" ' +
                        'data-module="' + mi + '" data-lesson="' + li + '">&times;</button>' +
                    '</div>';
                body.appendChild(row);
            });

            // Add lesson button.
            const addLessonBtn = document.createElement('button');
            addLessonBtn.type = 'button';
            addLessonBtn.className = 'btn btn-sm btn-outline-secondary mt-1 lum-add-lesson';
            addLessonBtn.textContent = '+ ' + STRINGS.addlesson;
            addLessonBtn.setAttribute('data-module', mi);
            body.appendChild(addLessonBtn);

            container.appendChild(card);
        });

        // Add module button.
        const addModBtn = document.createElement('button');
        addModBtn.type = 'button';
        addModBtn.className = 'btn btn-outline-primary mb-3 lum-add-module';
        addModBtn.textContent = '+ ' + STRINGS.addmodule;
        container.appendChild(addModBtn);
    };

    /**
     * Synchronise input values back into the modules array and update the hidden field.
     */
    const sync = () => {
        container.querySelectorAll('.lumination-module').forEach((card, mi) => {
            if (!modules[mi]) {
                return;
            }
            const titleInput = card.querySelector('.lum-module-title');
            if (titleInput) {
                modules[mi].title = titleInput.value;
            }
            const lessonInputs = card.querySelectorAll('.lum-lesson-title');
            lessonInputs.forEach((inp, li) => {
                if (modules[mi].lessons && modules[mi].lessons[li]) {
                    modules[mi].lessons[li].title = inp.value;
                }
            });
        });
        hiddenField.value = JSON.stringify(modules);
    };

    // Event delegation.
    container.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) {
            return;
        }

        sync();

        if (btn.classList.contains('lum-add-module')) {
            modules.push({title: '', lessons: [{title: ''}]});
            render();
        } else if (btn.classList.contains('lum-remove-module')) {
            if (modules.length <= 1) {
                return;
            }
            modules.splice(parseInt(btn.dataset.index), 1);
            render();
        } else if (btn.classList.contains('lum-add-lesson')) {
            const mi = parseInt(btn.dataset.module);
            modules[mi].lessons.push({title: ''});
            render();
        } else if (btn.classList.contains('lum-remove-lesson')) {
            const mi = parseInt(btn.dataset.module);
            const li = parseInt(btn.dataset.lesson);
            if (modules[mi].lessons.length <= 1) {
                return;
            }
            modules[mi].lessons.splice(li, 1);
            render();
        }
        sync();
    });

    container.addEventListener('input', () => {
        sync();
    });

    // Loading overlay on form submit.
    const form = container.closest('form');
    if (form) {
        form.addEventListener('submit', () => {
            sync();
            const overlay = document.createElement('div');
            overlay.id = 'lumination-loading-overlay';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.6);' +
                'z-index:9999;display:flex;align-items:center;justify-content:center;';
            overlay.innerHTML =
                '<div style="background:#fff;border-radius:8px;padding:2rem 3rem;' +
                    'text-align:center;max-width:420px;">' +
                    '<div class="spinner-border text-primary mb-3" role="status">' +
                        '<span class="sr-only">Loading...</span></div>' +
                    '<h4>' + escapeHtml(STRINGS.generatingcourse) + '</h4>' +
                    '<p class="text-muted mb-0">' + escapeHtml(STRINGS.generatingcourse_desc) + '</p>' +
                '</div>';
            document.body.appendChild(overlay);
        });
    }

    render();
    sync();
};
