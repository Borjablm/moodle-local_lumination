# Changelog

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
