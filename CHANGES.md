# Changelog

## v0.3.0 (2026-07-08)

- Migrated to the AI Tutor API v1. The base URL is now a configurable admin setting, defaulting to the staging server (`https://stage.ai-tutor.ai/api/v1`).
- The course outline is now produced by the synchronous `/course/guide` endpoint, which returns a structured, editable outline (replacing the agent-chat markdown parsing).
- The reviewed outline is saved back to the guide via `PUT /course/guide/{id}`, and the Moodle course is built directly from the edited outline so your edits are respected exactly.
- Lesson content is generated via the asynchronous `/tutor` endpoint (submit + poll).
- Removed the `material-to-text` and `process-material` steps (no longer part of the API); documents are sent inline as base64.
- Course generation now takes a single source document.
- Auth header changed to `x-api-key`; usage logging reads `input_tokens`/`output_tokens`.

## v0.1.0 (2026-02-16)

- Initial alpha release.
- AI Course Generator: upload documents, generate outline, review/edit, create Moodle course.
- Text extraction via Lumination API material-to-text endpoint.
- Outline generation via Lumination agent chat endpoint.
- Lesson content generation via Lumination agent chat endpoint.
- Lesson title deduplication (prompt instruction + heading stripping safety net).
- Course creation with sections (modules) and page activities (lessons).
- Interactive outline editor with add/remove modules and lessons.
- Loading overlay during course generation.
- Multi-language support (English, Spanish, French, German, Portuguese, Italian, Dutch).
- API usage tracking: token counts and credits logged per API call.
- Admin usage dashboard with summary cards, daily/action/user breakdown tables.
- Navigation integration: nav drawer, site admin, category settings.
- Privacy API implementation.
- CI pipeline: GitHub Actions with PHP 8.1/8.2/8.3, PostgreSQL, Moodle 4.5.
