# Drupal servers: Composer and file permissions runbook

How Composer and file permissions work on lib.lsu.edu (production) and libstage.lsu.edu, what broke on 2026-10-22, and what we changed so it doesn't happen again.

## Server facts

| | |
|---|---|
| Site root | `/var/www/html/drupal_site` (the folder with `composer.json`) |
| Web root | `/var/www/html/drupal_site/web` |
| Deploy user | `fossa` |
| Web server | Apache (`httpd`), runs as user `apache` |
| Shared group | `www-data`. `apache` is a member of it, and so is `fossa` (as its primary group since 2026-10-22) |
| Ownership scheme | Code: `fossa:www-data`, files `640`, dirs `750`. Uploads (`sites/*/files`): group-writable, setgid |
| Git remote | `git@github.com:lsulibraries/lsu-libraries-composer.git`, branch `main` |

## What happened on 2026-10-22

1. **Composer seemed to hang.** Every `require` or `update` fetches all 19 `lsulibraries/*` repos over SSH, one at a time. The cause is `"no-api": true` on each repo plus `"github-protocols": ["ssh"]` in `composer.json`. Some of those SSH connections stalled for good.
2. **Production and `origin/main` had diverged**, because someone committed on production while someone else pushed from elsewhere. Pulling conflicted on `composer.lock`. We aborted the merge, saved production's commit on a backup branch, reset to `origin/main`, and redid the change on top of it.
3. **The core update took the site down with a 403**: "Server unable to read htaccess file". Composer rewrote `web/.htaccess` as `fossa:fossa 640`. `fossa`'s primary group was `fossa`, so Apache, which reads through `www-data`, couldn't open it.
4. **`drush cr` failed on libstage** with "Failed to open directory: Permission denied". Apache had created Twig cache folders as `apache:apache 770`, which `fossa` can't open.

## Permanent fixes now in place

### 1. `fossa`'s primary group is `www-data` (both servers)

New files `fossa` creates, including everything Composer writes, now come out as `fossa:www-data`, so Apache can read them with no extra step.

How it was done, in case it ever needs redoing:

```bash
ls -ld ~                          # home must be owner-only (drwx------)
sudo usermod -g www-data fossa
```

Then reconnect. In VS Code, run **Remote-SSH: Kill VS Code Server on Host**, then reconnect. Check it:

```bash
id -gn                            # → www-data
touch web/perm-test && ls -l web/perm-test && rm web/perm-test   # → fossa www-data
```

### 2. setgid on the uploads folders

`drupal_fix_permissions.sh` was run with `-s`, so folders Apache creates under `sites/*/files` take the `www-data` group.

### 3. `cx`: Composer over HTTPS instead of SSH

The alias rewrites `git@github.com:` to `https://github.com/` for Composer's git calls only. `git push` still uses SSH.

```bash
alias cx="GIT_TERMINAL_PROMPT=0 GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0='url.https://github.com/.insteadOf' GIT_CONFIG_VALUE_0='git@github.com:' COMPOSER_MEMORY_LIMIT=-1 composer"
```

To keep it across logins, add that line to `~/.bashrc` on both servers. The lsulibraries repos are public, so HTTPS needs no login.

## Adding a contrib module

Test on libstage first, then let production install from the committed lock. That way production never has to resolve dependencies itself, which is the slow step.

### On libstage

```bash
git status --short                # must be clean
git pull
cx require drupal/<module> --dry-run
```

Check the dry run: only `Installing` lines for the module and its dependencies, and no `Upgrading` or `Removing`.

```bash
cx require drupal/<module>
drush en <module> -y && drush cr
```

Test it in the browser, then check the log:

```bash
drush watchdog:show --severity=Error --count=10
```

Commit and push **right away**:

```bash
git add composer.json composer.lock && git commit -m "Add drupal/<module>" && git fetch && git status -sb
git push                          # only if it says [ahead 1], not "behind"
```

### On production

```bash
git pull && git log --oneline -1  # should show the libstage commit
cx install --dry-run              # must match what libstage installed
drush sql:dump --gzip --result-file=../backup-pre-<module>-$(date +%F).sql
cx install
drush en <module> -y && drush updb -y && drush cr
```

Load the homepage and an admin page, then check the log.

Module configuration (widgets, image styles and so on) is done by hand in the production UI.

## Core updates

Same flow as a module, plus maintenance mode on production:

```bash
cx update "drupal/core-*" --with-all-dependencies --dry-run    # on libstage first
```

On production, after `git pull`:

```bash
drush sql:dump --gzip --result-file=../backup-pre-core-$(date +%F).sql
drush state:set system.maintenance_mode 1 --input-format=integer && drush cr
cx install
drush updb -y && drush cr
drush state:set system.maintenance_mode 0 --input-format=integer && drush cr
```

`composer.json` applies core patches with `composer-exit-on-patch-failure: true`. If a patch no longer applies, the install stops partway, so stay in maintenance mode and fix or remove that patch.

## Git rules for Composer changes

- `git pull` before any Composer change, and `git push` straight after committing.
- Only one person makes Composer changes at a time. Two people changing `composer.lock` at once always conflicts.
- If a push is rejected, **don't `git pull`**. Look first:

  ```bash
  git fetch && git log --oneline --stat main..origin/main
  ```

  If the remote commit touched `composer.json` or `composer.lock`, rebuild on top of it:

  ```bash
  git branch backup/<name> HEAD
  git reset --hard origin/main      # vendor/ and web/modules/contrib are untouched
  cx require drupal/<module>        # redo your change on their lock
  git add composer.json composer.lock && git commit -m "..." && git push
  ```

- If a merge is already half-done with conflicts: `git merge --abort`.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Composer sits on `git remote show origin` / `ssh ... git-upload-pack` | Stalled SSH connection to GitHub | Use `cx`. If it's already stuck, find the `ssh` PID with `ps -ef --forest \| grep -A4 [c]omposer` and `kill` only that PID |
| 403 "Server unable to read htaccess file" | A file isn't readable by `www-data` | `stat -c '%U:%G %a %n' web/.htaccess`. Quick fix: `chmod 644 web/.htaccess`. Then check `id -gn` is `www-data` and run the repair script below |
| `drush cr`: "Failed to open directory: Permission denied" under `files/php/twig` | Apache-created cache folders in group `apache` | `sudo chgrp -R www-data /var/www/html/drupal_site/web/sites/default/files/php && drush cr`, or `sudo -u apache drush cr` |
| `Class ... not found` errors in the log at the moment of an install | Requests arrived while Composer was swapping files | Harmless if nothing newer appears. Check with `drush watchdog:show --type=php --count=5` |
| `[notice] Module X has an entry in the system.schema key/value storage, but is missing` | A removed module was never uninstalled | `drush php:eval "\Drupal::keyValue('system.schema')->delete('X');"` |

### Repair script (only if permissions get messed up)

Use this, for example, after someone runs Composer as another user:

```bash
sudo bash drupal_fix_permissions.sh -u=fossa -g=www-data -s -p=/var/www/html/drupal_site/web -n   # dry run
sudo bash drupal_fix_permissions.sh -u=fossa -g=www-data -s -p=/var/www/html/drupal_site/web
drush cr
```

- `-p` must be an **absolute** path. A relative `-p=web` fails the Drupal-root check.
- Use `-g=www-data`, **never** `-g=apache`, which would change the group on the whole site.
- The `find: paths must precede expression` lines are a bug in the script (an unquoted `sites/*/files` glob). They're harmless: that step just skips the chmod of code under `web`. The ownership fix still runs.

## Rollback

```bash
git reset --hard <previous-commit-or-backup-branch> && cx install
gunzip -c ../backup-<name>.sql.gz | drush sql:cli
drush cr
```

## Open items (as of 2026-10-22)

- **Custom Field:** 4.x no longer supports core 11.4 (4.0.12 needs `<11.4`). 4.0.8 still runs, but move to `^5.0` after testing on libstage and reading the 5.0 release notes.
- **`composer audit`:** 3 advisories affecting 2 packages.
- **linkchecker:** "cURL error 60: SSL certificate problem" on the server's own URLs, likely the server's CA bundle.
- **`repository.lsu.edu`** answers `api_proxy` requests with a 403 bot challenge ("Just a moment…").
- **`composer.json` cleanup:** remove `"no-api": true` or add a GitHub token so Composer uses the API; tag releases for the 19 `dev-main` custom modules; check whether the "CKEditor 5 and Tour with BigPipe" core patch is still needed; consider `composer install --no-dev` on production (it currently installs `devel`, `devel_php` and `phpstan`) after checking `devel` isn't enabled.
- **libstage Apache:** creates cache folders without group access. If `drush cr` keeps failing there, ask for `UMask=0007` on `httpd`.
