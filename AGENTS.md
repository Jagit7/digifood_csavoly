# DigiFood project conventions

## Database strings

- The MySQL database uses the `utf8mb4_unicode_ci` collation.
- Every new Laravel migration must declare string column lengths explicitly.
- A `string`/`VARCHAR` column must never be longer than 191 characters.
- Prefer shorter, domain-appropriate limits for identifiers, codes, phone numbers, postal codes, enum-like values, and other constrained fields.
- Matching request validation rules must use the same maximum length as the database column.
- The project's own source files are UTF-8 encoded. Blade, PHP, JavaScript, and translation files containing Hungarian text must not be read with a different character encoding or rewritten wholesale through tools that do not preserve UTF-8 exactly.
- Before and after edits, check for mojibake markers such as `Ă`, `Ă…`, `Ă„`, `Ă‚`, or `Ă˘`.
- Modify these files with small, targeted patches whenever possible.
