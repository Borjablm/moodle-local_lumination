# Lumination AI for Moodle -- Local Plugin

Moodle plugin (`local_lumination`) that generates full courses from uploaded documents using the Lumination AI API. Targets the **Moodle Plugin Directory** for public distribution.

## Project Structure

```
local/lumination/
  course_generator.php          # Step 1 page: upload documents, extract text, generate outline
  review_outline.php            # Step 2 page: review/edit outline, create Moodle course
  usage.php                     # Admin usage dashboard (token/credit tracking)
  settings.php                  # Admin settings (API key only)
  lib.php                       # Navigation hooks (nav drawer, category settings)
  version.php                   # Plugin version (2026021602, Moodle 4.4+)
  classes/
    api_client.php              # HTTP client for Lumination API (hardcoded base URL)
    course_generator.php        # Core logic: outline generation, lesson content, Moodle course creation
    document_manager.php        # File upload handling, text extraction via API
    usage_logger.php            # Logs API token/credit usage to DB, aggregation queries
    form/
      upload_form.php           # Step 1 moodleform (title, documents, language, category)
      review_outline_form.php   # Step 2 moodleform (outline editor, hidden JSON field)
    privacy/
      provider.php              # Privacy API: declares external data sent to Lumination API
  db/
    access.php                  # Capabilities: manage, generatecourse, viewusage
    install.xml                 # DB schema: local_lumination_usage table
    upgrade.php                 # Upgrade steps (creates usage table for existing installs)
  lang/en/
    local_lumination.php        # All English strings (sorted alphabetically)
  tests/
    api_client_test.php         # Tests is_configured() with/without API key
    course_generator_test.php   # Tests outline parsing, shortname generation, course creation
    usage_logger_test.php       # Tests usage logging and aggregation queries
  .github/workflows/
    ci.yml                      # Moodle Plugin CI: PHP 8.1/8.2/8.3, MOODLE_405_STABLE, PostgreSQL
```

## Development Environment

- **Docker**: `docker-compose.yml` at repo root runs Moodle + PostgreSQL
  - Moodle: `moodlehq/moodle-php-apache:8.1` on port 8080
  - DB: PostgreSQL 16
  - Container: `moodle-lumination-moodle-1`
- **Git repo**: `c:\Dev\moodle-lumination\local\lumination\` (the plugin code)
- **Moodle install**: `c:\Dev\moodle-lumination\moodle\` (mounted into Docker)
- **Sync required**: After editing plugin files, copy to `moodle/local/lumination/` then reload the page. Use: `cp -r local/lumination/* moodle/local/lumination/`
- **GitHub**: https://github.com/Borjablm/moodle-local_lumination (account: Borjablm)

## Lumination API

- **Base URL**: `https://ai-sv-production.lumination.ai` (hardcoded in `api_client.php`)
- **Auth**: `X-API-KEY` header
- **Endpoints used**:
  - `POST /lumination-ai/api/v1/process-material` -- upload documents for text extraction
  - `POST /lumination-ai/api/v1/agent/chat` -- generate outlines and lesson content
- **Response metrics**: `token_count_input`, `token_count_output`, `credits_charged`
- **Response text**: nested at `response.response` (double nested)
- See `c:\Dev\wp-lumination-chatbot\docs\api-notes.md` for full API docs

## CI Pipeline

GitHub Actions using `moodlehq/moodle-plugin-ci ^4`:

- **Matrix**: PHP 8.1, 8.2, 8.3 x MOODLE_405_STABLE
- **DB**: PostgreSQL (not MySQL -- all SQL must be cross-DB compatible)
- **Checks**: PHP Lint, Copy/Paste Detector, Mess Detector, Code Checker (`--max-warnings 0`), PHPDoc Checker (`--max-warnings 0`), Validate, Savepoints, Mustache, Grunt, PHPUnit
- **Zero tolerance**: `--max-warnings 0` means even warnings fail CI

## Moodle Coding Standards (Lessons Learned)

These are the phpcs rules enforced by `moodle-plugin-ci codechecker`. They differ from standard PSR-12.

### MOODLE_INTERNAL check rules

- **Files with side effects** (require_once, global variable assignment like `$capabilities`): MUST have `defined('MOODLE_INTERNAL') || die();`
- **Files with only class/function definitions** (no side effects): must NOT have `MOODLE_INTERNAL` -- it triggers a warning
- **Test files**: must NOT have `MOODLE_INTERNAL`
- **Page entry points** (files that do `require_once('config.php')`): must NOT have `MOODLE_INTERNAL`
- **Form files** with `require_once($CFG->libdir . '/formslib.php')`: this IS a side effect, so they need `MOODLE_INTERNAL`

### Class and method formatting

- No blank line after opening brace of class definition
- No blank line after opening brace of method with multi-line signature
- No blank line before closing brace of control structures (before `} catch`, `} else`, etc.)
- Test classes MUST be `final`
- Use `else if` not `elseif`

### Multi-line function calls

One argument per line, closing paren on its own line:
```php
$result = some_function(
    $arg1,
    $arg2,
    $arg3
);
```

### Strings and comments

- No backticks in strings -- use `chr(96)` to build patterns containing backticks
- No em-dashes (`--`) in strings or comments -- use `--` instead
- Inline comments must end with `.`, `!`, or `?`
- Max 132 character line length
- `unset()` should use separate calls per argument

### Empty catch blocks

- phpcs forbids empty catch blocks (comments alone don't count)
- `debugging()` in catch blocks causes PHPUnit "unexpected debugging call" errors
- Solution: use `unset($e);` as a no-op statement in intentionally empty catches

### Form files (moodleform)

- `\moodleform` is NOT autoloaded in Moodle -- requires explicit `require_once($CFG->libdir . '/formslib.php');`
- Must declare `global $CFG;` before the require_once (because the file is namespaced)
- Pattern:
  ```php
  namespace local_lumination\form;

  defined('MOODLE_INTERNAL') || die();

  global $CFG;
  require_once($CFG->libdir . '/formslib.php');
  ```

## SQL Compatibility

- **No MySQL-only functions**: `FROM_UNIXTIME()` does not exist in PostgreSQL
- For date grouping: use `FLOOR(timecreated / 86400)` then convert to date string in PHP
- **No subqueries with ORDER BY in FROM clause**: PostgreSQL handles these differently
- Use direct JOIN + GROUP BY instead of subquery patterns
- Moodle uses `{tablename}` syntax for table references (auto-prefixed)

## PHPUnit Testing

- Tests extend `\advanced_testcase` for full Moodle test framework support
- Call `$this->resetAfterTest()` in setUp
- Mock `api_client` with `$this->createMock(api_client::class)`
- **Heading stripping**: the lesson content generator strips leading `<h1>`-`<h4>` headings, so mock responses should not include them (or assertions should account for stripping)
- `debugging()` calls in production code cause "unexpected debugging call" errors in tests unless explicitly expected
- Moodle's `$this->assertDebugging()` method exists on `\advanced_testcase` but be aware of calling patterns

## Database

- **Table**: `local_lumination_usage` (defined in `db/install.xml`)
- **Columns**: id, userid, courseid, action, tokens_in, tokens_out, credits, timecreated
- **Upgrade path**: `db/upgrade.php` creates the table for existing installs
- **Version bumps**: increment `$plugin->version` in `version.php`, then run upgrade via `/admin/index.php` or CLI: `docker exec moodle-lumination-moodle-1 bash -c "php /var/www/html/admin/cli/upgrade.php --non-interactive"`

## Capabilities

| Capability | Who | Purpose |
|-----------|-----|---------|
| `local/lumination:manage` | Manager | Access admin settings |
| `local/lumination:generatecourse` | Manager, Editing Teacher | Use the course generator |
| `local/lumination:viewusage` | Manager | View usage dashboard |

## Key Workflows

### Course generation flow

1. User uploads documents on `course_generator.php`
2. `document_manager::file_to_text()` extracts text via `/process-material` API
3. `course_generator::generate_outline_from_text()` generates outline via `/agent/chat`
4. Outline stored in `$SESSION`, redirect to `review_outline.php`
5. User edits outline (JS editor), submits form
6. `course_generator::create_moodle_course_from_text()` creates Moodle course + sections + page activities
7. Each lesson's content generated via `/agent/chat` with source text context
8. Usage logged to `local_lumination_usage` after each API call

### Lesson title deduplication

The AI prompt explicitly says not to include the lesson title as a heading. As a safety net, the code strips leading `<h1>`-`<h4>` and `##`-style markdown headings from generated content.
