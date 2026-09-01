# Test themes

Two throwaway themes used by `tests/smoke/scenario-theme-scanner.php`.

| Theme | Behaviour |
| ----- | --------- |
| `dc-correct` | Calls `comments_template()` the way WordPress expects, so the plugin can substitute its empty template. The scanner must report this one **clean**. |
| `dc-broken` | Draws the comment area itself, without calling `comments_template()` and without checking `comments_open()`. This reproduces the plugin's most common support issue — the one the top FAQ has described in prose for a decade. The scanner must report **theme_ignores_template**. |

`bin/smoke-test.sh` copies both into the sandbox before running the scanner
scenario. They are never shipped: `tests/` is in `.distignore`.
